import asyncio
import hashlib
import json
import logging
from abc import ABC, abstractmethod
from collections.abc import Mapping
from datetime import UTC, datetime
from typing import Any

from app.config import Settings

logger = logging.getLogger(__name__)


class ArtifactStore(ABC):
    @abstractmethod
    async def persist(
        self,
        artifact_type: str,
        request_payload: Mapping[str, Any],
        response_payload: Mapping[str, Any],
        metadata: Mapping[str, Any] | None = None,
    ) -> None:
        raise NotImplementedError

    @abstractmethod
    async def persist_binary(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: Mapping[str, Any] | None = None,
        *,
        extension: str = "bin",
        content_type: str = "application/octet-stream",
    ) -> str | None:
        raise NotImplementedError


class NoOpArtifactStore(ArtifactStore):
    async def persist(
        self,
        artifact_type: str,
        request_payload: Mapping[str, Any],
        response_payload: Mapping[str, Any],
        metadata: Mapping[str, Any] | None = None,
    ) -> None:
        return None

    async def persist_binary(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: Mapping[str, Any] | None = None,
        *,
        extension: str = "bin",
        content_type: str = "application/octet-stream",
    ) -> str | None:
        return None


class S3ArtifactStore(ArtifactStore):
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._client = None
        # Separate boto3 client used ONLY for ``generate_presigned_url`` when
        # ``settings.artifact_s3_public_endpoint`` is set. The presigned URL
        # host is taken from the client's ``endpoint_url``, so signing against
        # this client yields a browser-reachable URL (Caddy proxies the public
        # path back to internal MinIO). Uploads continue to use ``_client``.
        self._public_client = None

    async def persist(
        self,
        artifact_type: str,
        request_payload: Mapping[str, Any],
        response_payload: Mapping[str, Any],
        metadata: Mapping[str, Any] | None = None,
    ) -> None:
        await asyncio.to_thread(
            self._persist_sync,
            artifact_type,
            dict(request_payload),
            dict(response_payload),
            dict(metadata or {}),
        )

    async def persist_binary(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: Mapping[str, Any] | None = None,
        *,
        extension: str = "bin",
        content_type: str = "application/octet-stream",
    ) -> str | None:
        return await asyncio.to_thread(
            self._persist_binary_sync,
            artifact_type,
            binary_payload,
            dict(metadata or {}),
            extension,
            content_type,
        )

    async def signed_url(self, key: str, ttl_seconds: int = 300) -> str:
        """Return a presigned HTTPS GET URL for an object key.

        Foundation for replacing inline ``data:audio/wav;base64,...`` payloads
        with short-lived MinIO URLs in the S2S TTS write path. Wiring is a
        separate iteration; this method is intentionally standalone.
        """
        return await asyncio.to_thread(self._signed_url_sync, key, ttl_seconds)

    def _signed_url_sync(self, key: str, ttl_seconds: int) -> str:
        signer = self._public_client_for() or self._client_for()
        return signer.generate_presigned_url(
            "get_object",
            Params={"Bucket": self._settings.artifact_s3_bucket, "Key": key},
            ExpiresIn=ttl_seconds,
        )

    def _persist_sync(
        self,
        artifact_type: str,
        request_payload: dict[str, Any],
        response_payload: dict[str, Any],
        metadata: dict[str, Any],
    ) -> None:
        payload = {
            "artifact_type": artifact_type,
            "request": request_payload,
            "response": response_payload,
            "metadata": metadata,
            "created_at": datetime.now(UTC).isoformat(),
        }
        body = self._canonical_json(payload).encode("utf-8")
        sha256 = hashlib.sha256(body).hexdigest()
        key = self._object_key(artifact_type, sha256)

        self._client_for().put_object(
            Bucket=self._settings.artifact_s3_bucket,
            Key=key,
            Body=body,
            ContentType="application/json",
            Metadata={
                "artifact-type": artifact_type,
                "sha256": sha256,
            },
        )

    def _persist_binary_sync(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: dict[str, Any],
        extension: str,
        content_type: str,
    ) -> str:
        sha256 = hashlib.sha256(binary_payload).hexdigest()
        key = self._binary_object_key(artifact_type, sha256, extension)
        object_metadata = {
            "artifact-type": artifact_type,
            "sha256": sha256,
        }
        object_metadata.update(
            {k.replace("_", "-"): str(v) for k, v in metadata.items() if v is not None}
        )
        self._client_for().put_object(
            Bucket=self._settings.artifact_s3_bucket,
            Key=key,
            Body=binary_payload,
            ContentType=content_type,
            Metadata=object_metadata,
        )
        return f"s3://{self._settings.artifact_s3_bucket}/{key}"

    def _object_key(self, artifact_type: str, sha256: str) -> str:
        timestamp = datetime.now(UTC).strftime("%Y/%m/%d/%H%M%S")
        prefix = self._settings.artifact_s3_prefix.strip("/")
        leaf = f"{artifact_type}/{timestamp}-{sha256[:12]}.json"
        return f"{prefix}/{leaf}" if prefix else leaf

    def _binary_object_key(self, artifact_type: str, sha256: str, extension: str) -> str:
        timestamp = datetime.now(UTC).strftime("%Y/%m/%d/%H%M%S")
        prefix = self._settings.artifact_s3_prefix.strip("/")
        leaf = f"{artifact_type}/{timestamp}-{sha256[:12]}.{extension.lstrip('.')}"
        return f"{prefix}/{leaf}" if prefix else leaf

    def _client_for(self):  # type: ignore[no-untyped-def]
        if self._client is not None:
            return self._client

        try:
            import boto3
        except ImportError as exc:  # pragma: no cover
            raise RuntimeError("boto3 is not installed") from exc

        self._client = boto3.client(
            "s3",
            endpoint_url=self._settings.artifact_s3_endpoint,
            aws_access_key_id=self._settings.artifact_s3_access_key,
            aws_secret_access_key=self._settings.artifact_s3_secret_key,
            region_name=self._settings.artifact_s3_region,
            config=boto3.session.Config(s3={"addressing_style": "path" if self._settings.artifact_s3_force_path_style else "virtual"}),
        )
        return self._client

    def _public_client_for(self):  # type: ignore[no-untyped-def]
        """Boto3 client pinned to the public endpoint, used only for presigning.

        Returns ``None`` when ``artifact_s3_public_endpoint`` is unset so the
        caller falls back to ``_client`` (preserves the legacy behaviour for
        dev environments that don't have a Caddy proxy in front of MinIO).
        """
        public_endpoint = self._settings.artifact_s3_public_endpoint
        if not public_endpoint:
            return None
        if self._public_client is not None:
            return self._public_client

        try:
            import boto3
        except ImportError as exc:  # pragma: no cover
            raise RuntimeError("boto3 is not installed") from exc

        self._public_client = boto3.client(
            "s3",
            endpoint_url=public_endpoint,
            aws_access_key_id=self._settings.artifact_s3_access_key,
            aws_secret_access_key=self._settings.artifact_s3_secret_key,
            region_name=self._settings.artifact_s3_region,
            config=boto3.session.Config(s3={"addressing_style": "path" if self._settings.artifact_s3_force_path_style else "virtual"}),
        )
        return self._public_client

    def _canonical_json(self, payload: dict[str, Any]) -> str:
        # Use a function-local import so background task serialization does not
        # depend on module-level globals surviving process reload edge cases.
        import json as jsonlib

        return jsonlib.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False)


class MongoArtifactStore(ArtifactStore):
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._client = None
        self._collection = None

    async def persist(
        self,
        artifact_type: str,
        request_payload: Mapping[str, Any],
        response_payload: Mapping[str, Any],
        metadata: Mapping[str, Any] | None = None,
    ) -> None:
        await asyncio.to_thread(
            self._persist_sync,
            artifact_type,
            dict(request_payload),
            dict(response_payload),
            dict(metadata or {}),
        )

    async def persist_binary(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: Mapping[str, Any] | None = None,
        *,
        extension: str = "bin",
        content_type: str = "application/octet-stream",
    ) -> str | None:
        return await asyncio.to_thread(
            self._persist_binary_sync,
            artifact_type,
            binary_payload,
            dict(metadata or {}),
            extension,
            content_type,
        )

    def _persist_sync(
        self,
        artifact_type: str,
        request_payload: dict[str, Any],
        response_payload: dict[str, Any],
        metadata: dict[str, Any],
    ) -> None:
        collection = self._get_collection()
        collection.insert_one(
            {
                "artifact_type": artifact_type,
                "request": request_payload,
                "response": response_payload,
                "metadata": metadata,
                "created_at": datetime.now(UTC),
            }
        )

    def _persist_binary_sync(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: dict[str, Any],
        extension: str,
        content_type: str,
    ) -> str | None:
        collection = self._get_collection()
        sha256 = hashlib.sha256(binary_payload).hexdigest()
        collection.insert_one(
            {
                "artifact_type": artifact_type,
                "binary_payload_hex": binary_payload.hex(),
                "metadata": metadata,
                "sha256": sha256,
                "extension": extension,
                "content_type": content_type,
                "created_at": datetime.now(UTC),
            }
        )
        return f"mongo://{self._settings.mongodb_collection_name}/{sha256}.{extension.lstrip('.')}"

    def _get_collection(self):  # type: ignore[no-untyped-def]
        if self._collection is not None:
            return self._collection

        try:
            from pymongo import MongoClient
        except ImportError as exc:  # pragma: no cover
            raise RuntimeError("pymongo is not installed") from exc

        self._client = MongoClient(
            self._settings.mongodb_uri,
            serverSelectionTimeoutMS=int(self._settings.mongodb_timeout_seconds * 1000),
        )
        database = self._client.get_default_database()
        if database is None:  # pragma: no cover
            raise RuntimeError("mongodb_uri must include a default database name")
        self._collection = database[self._settings.mongodb_collection_name]
        return self._collection


def build_artifact_store(settings: Settings) -> ArtifactStore:
    if not settings.mongodb_trace_writes_enabled:
        return NoOpArtifactStore()

    if (
        settings.artifact_s3_endpoint
        and settings.artifact_s3_access_key
        and settings.artifact_s3_secret_key
        and settings.artifact_s3_bucket
    ):
        try:
            return S3ArtifactStore(settings)
        except Exception as exc:  # pragma: no cover
            logger.warning("failed to initialize S3 artifact store: %s", exc)

    if not settings.mongodb_uri:
        return NoOpArtifactStore()

    try:
        return MongoArtifactStore(settings)
    except Exception as exc:  # pragma: no cover
        logger.warning("failed to initialize Mongo artifact store: %s", exc)
        return NoOpArtifactStore()
