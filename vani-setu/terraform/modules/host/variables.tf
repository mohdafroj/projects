variable "environment" { type = string }
variable "ssh_user" { type = string }
variable "ssh_key_path" { type = string }
variable "app_host_ip" { type = string }
variable "shared_host_ip" { type = string }
variable "enable_changes" { type = bool }
variable "required_ports" { type = list(number) }
variable "required_tools" { type = list(string) }
