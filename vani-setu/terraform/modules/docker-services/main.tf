resource "local_file" "plan" {
  filename = "${path.root}/.generated/${var.environment}-docker-services.json"
  content = jsonencode({
    environment = var.environment
    services    = var.services
    note        = "Auxiliary Docker services remain Docker-managed. Confirm env files, secrets, volumes, health checks, restart policy, and owner before recreation."
  })
}

resource "null_resource" "compose_preflight" {
  for_each = var.enable_recreate ? { for svc in var.services : svc.name => svc } : {}

  triggers = {
    image        = each.value.image
    compose_hint = each.value.compose_hint
  }

  connection {
    type        = "ssh"
    host        = each.value.host
    user        = var.ssh_user
    private_key = file(var.ssh_key_path)
  }

  provisioner "remote-exec" {
    inline = [
      "set -eu",
      "test -f '${each.value.compose_hint}'",
      "docker compose -f '${each.value.compose_hint}' config >/dev/null",
    ]
  }
}
