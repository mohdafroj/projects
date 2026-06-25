locals {
  baseline = {
    environment    = var.environment
    app_host_ip    = var.app_host_ip
    shared_host_ip = var.shared_host_ip
    required_tools = var.required_tools
    required_ports = var.required_ports
  }
}

resource "local_file" "baseline" {
  filename = "${path.root}/.generated/${var.environment}-host-baseline.json"
  content  = jsonencode(local.baseline)
}

resource "null_resource" "preflight" {
  for_each = var.enable_changes ? {
    app    = var.app_host_ip
    shared = var.shared_host_ip
  } : {}

  connection {
    type        = "ssh"
    host        = each.value
    user        = var.ssh_user
    private_key = file(var.ssh_key_path)
  }

  provisioner "remote-exec" {
    inline = [
      "set -eu",
      "hostname",
      "command -v docker",
      "docker compose version",
      "command -v kubectl || true",
      "systemctl is-active docker",
      "systemctl is-active cron",
      "systemctl is-active ufw || true",
    ]
  }
}
