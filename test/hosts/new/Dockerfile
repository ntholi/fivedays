# Stand-in for the new, bare Lightsail host (Ubuntu 24.04): systemd as PID 1, sshd, user `ubuntu` with
# passwordless sudo. Only what Lightsail's Ubuntu 24.04 image already ships is installed here; Docker,
# unattended-upgrades and the rest come from `just provision`, exactly as on the real host.
# Started by test/hosts/standin (privileged, own cgroup namespace, /var/lib/docker on a throwaway volume).
FROM ubuntu:noble-20251001

ENV container=docker
# socat is test harness only (not on Lightsail): it forwards the stand-in's 127.0.0.1:<registry port> to
# the throwaway registry, so dockerd pulls from a loopback registry, which Docker trusts without config.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      systemd systemd-sysv dbus openssh-server sudo ca-certificates curl gnupg python3 \
      ufw iptables cron lsb-release kmod iproute2 procps less socat \
 && rm -rf /var/lib/apt/lists/*

# Lightsail's cloud-init gives the default user passwordless sudo the same way.
RUN echo 'ubuntu ALL=(ALL) NOPASSWD:ALL' >/etc/sudoers.d/90-cloud-init-users \
 && chmod 440 /etc/sudoers.d/90-cloud-init-users \
 && install -d -m 700 -o ubuntu -g ubuntu /home/ubuntu/.ssh \
 && printf 'PasswordAuthentication no\nKbdInteractiveAuthentication no\n' >/etc/ssh/sshd_config.d/60-standin.conf

# The container is privileged, so /proc/sys is the laptop's kernel. systemd-sysctl would apply Ubuntu's
# /etc/sysctl.d to the laptop at boot: masked. The others do nothing useful in a container.
RUN systemctl mask systemd-sysctl.service systemd-udevd.service systemd-udevd-kernel.socket \
      systemd-udevd-control.socket systemd-modules-load.service getty.target console-getty.service \
      systemd-remount-fs.service sys-kernel-debug.mount sys-kernel-tracing.mount \
 && systemctl enable ssh.socket

STOPSIGNAL SIGRTMIN+3
CMD ["/sbin/init"]
