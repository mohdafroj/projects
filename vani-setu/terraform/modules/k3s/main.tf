resource "null_resource" "install_k3s" {
  count = var.enabled ? 1 : 0

  triggers = {
    cluster_name = var.cluster_name
    node_name    = var.node_name
    k3s_version  = var.k3s_version
  }

  connection {
    type        = "ssh"
    host        = var.node_ip
    user        = var.ssh_user
    private_key = file(var.ssh_key_path)
  }

  provisioner "file" {
    source      = "${path.module}/scripts/install-k3s.sh"
    destination = "/tmp/sds-install-k3s.sh"
  }

  provisioner "remote-exec" {
    inline = [
      "chmod +x /tmp/sds-install-k3s.sh",
      "sudo K3S_VERSION='${var.k3s_version}' NODE_NAME='${var.node_name}' /tmp/sds-install-k3s.sh",
    ]
  }
}

resource "local_file" "kubeconfig_hint" {
  filename = "${path.root}/.generated/${var.environment}-kubeconfig-note.txt"
  content  = "Fetch kubeconfig from ${var.node_ip}:/etc/rancher/k3s/k3s.yaml after k3s installation."
}
