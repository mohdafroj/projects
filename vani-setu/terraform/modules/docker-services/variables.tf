variable "environment" { type = string }
variable "enable_recreate" { type = bool }
variable "ssh_user" { type = string }
variable "ssh_key_path" { type = string }
variable "services" {
  type = list(object({
    group        = string
    name         = string
    image        = string
    host         = string
    ports        = list(string)
    compose_hint = string
  }))
}
