# Terraform parallel environment deployment

This Terraform tree implements the draft plan in
`/gov/Terraform/Environment_Deployment_Plan (DRAFT).pdf`.

Terraform owns platform preparation: host baseline checks, k3s bootstrap hooks,
namespaces, storage defaults, platform tools, GitOps bootstrap, and
Docker-managed auxiliary service recreation plans. Argo CD owns Kubernetes
application workload deployment from approved Git repositories.

## Environments

The same module stack is used for:

- `envs/staging`
- `envs/uat`
- `envs/prod`

Each environment has its own `terraform.tfvars`. Staging, UAT, and PROD are
currently seeded with the known dev topology:

- app tier: `10.21.217.17`
- shared-services tier: `10.21.217.132`

PROD apply is intentionally blocked by default while production IPs, DNS/TLS,
secrets, storage, registry, backup ownership, and approvals are still pending.

## Usage

```bash
cd terraform/envs/staging
terraform init
terraform validate
terraform plan
```

Use `enable_remote_changes = true` only after the environment input form and
approval checklist are complete. Keep secrets out of Terraform variables and
state; use Vault paths, secret names, or external env files instead.
