from app.config import get_settings


def main() -> int:
    key = get_settings().sarvam_key()
    if not key:
        print("SARVAM_API_KEY missing")
        return 0
    print("SARVAM_API_KEY present")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
