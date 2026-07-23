# --- 1/3: AMI選定 ---
data "aws_ami" "al2023_arm" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-*-arm64"]
  }

  filter {
    name   = "architecture"
    values = ["arm64"]
  }
}

# --- 2/3: SG + Instance ---
resource "aws_security_group" "nat" {
  name        = "${var.project_name}-nat-instance-sg"
  description = "NAT instance for private subnet outbound"
  vpc_id      = var.vpc_id

  ingress {
    description = "Allow all traffic from private subnets"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = [var.private_cidr_block]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_instance" "nat" {
  ami                    = data.aws_ami.al2023_arm.id
  instance_type          = "t4g.nano"
  subnet_id              = var.public_subnet_id
  vpc_security_group_ids = [aws_security_group.nat.id]
  source_dest_check      = false

  # fck-nat(https://github.com/AndrewGuenther/fck-nat)の簡易実装。
  # iptables MASQUERADEルールをcloud-initで投入するだけでなく、
  # systemdユニットで起動のたびに冪等に再適用することで、
  # インスタンス再起動後もNAT機能を復元する。
  user_data = <<-EOF
    #!/bin/bash
    set -e

    if ! command -v iptables &>/dev/null; then
      dnf install -y iptables-nft
    fi

    echo 1 > /proc/sys/net/ipv4/ip_forward
    sysctl -w net.ipv4.ip_forward=1
    echo "net.ipv4.ip_forward = 1" >> /etc/sysctl.conf

    iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE

    cat <<'UNIT' > /etc/systemd/system/nat-masquerade.service
    [Unit]
    Description=Re-apply NAT MASQUERADE rule on boot
    After=network.target

    [Service]
    Type=oneshot
    ExecStart=/sbin/iptables -t nat -C POSTROUTING -o eth0 -j MASQUERADE || /sbin/iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
    RemainAfterExit=true

    [Install]
    WantedBy=multi-user.target
    UNIT

    systemctl daemon-reload
    systemctl enable nat-masquerade.service
    EOF

  tags = {
    Name = "${var.project_name}-nat-instance"
  }
}

resource "aws_eip" "nat" {
  instance = aws_instance.nat.id
  domain   = "vpc"
}

# --- 3/3: ルート追加 ---
resource "aws_route" "private_to_nat" {
  route_table_id         = var.private_route_table_id
  destination_cidr_block = "0.0.0.0/0"
  network_interface_id   = aws_instance.nat.primary_network_interface_id
}
