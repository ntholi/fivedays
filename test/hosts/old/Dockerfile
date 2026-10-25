# Stand-in for the OLD prod host (luct.click), laid out like the pre-check report says it is:
# Ubuntu 24.04, nginx 1.24, php8.3-fpm (service php8.3-fpm), MariaDB 10.11 (Ubuntu's own packages), Moodle
# at /var/www/html/sites/moodle (web root public/), dataroot /var/www/data/moodledata, /root/.my.cnf with a
# read-only backupuser, the www-data cron line and the root mysqldump cron. systemd is PID 1 like the
# new-host stand-in. Moodle code, data and certificates are put in by test/hosts/old/after-up.
FROM ubuntu:noble-20251001

ENV container=docker
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      systemd systemd-sysv dbus openssh-server sudo ca-certificates curl gnupg python3 \
      cron lsb-release kmod iproute2 procps less rsync openssl gzip \
      nginx mariadb-server mariadb-client \
      php8.3-fpm php8.3-cli php8.3-mysql php8.3-intl php8.3-zip php8.3-gd php8.3-soap php8.3-xml \
      php8.3-mbstring php8.3-curl php8.3-opcache \
 && rm -rf /var/lib/apt/lists/*

RUN echo 'ubuntu ALL=(ALL) NOPASSWD:ALL' >/etc/sudoers.d/90-cloud-init-users \
 && chmod 440 /etc/sudoers.d/90-cloud-init-users \
 && install -d -m 700 -o ubuntu -g ubuntu /home/ubuntu/.ssh \
 && printf 'PasswordAuthentication no\nKbdInteractiveAuthentication no\n' >/etc/ssh/sshd_config.d/60-standin.conf

# Moodle's PHP requirements (max_input_vars >= 5000) for fpm and cli, like a Moodle install guide sets them.
RUN printf 'max_input_vars = 5000\nupload_max_filesize = 25M\npost_max_size = 25M\nmemory_limit = 256M\n' \
      | tee /etc/php/8.3/fpm/conf.d/90-moodle.ini >/etc/php/8.3/cli/conf.d/90-moodle.ini \
 && printf '[mysqld]\ninnodb_buffer_pool_size = 512M\ninnodb_flush_log_at_trx_commit = 2\nmax_allowed_packet = 256M\n' \
      >/etc/mysql/mariadb.conf.d/90-standin.cnf

COPY nginx-moodle.conf /etc/nginx/sites-available/moodle
RUN rm -f /etc/nginx/sites-enabled/default && ln -s /etc/nginx/sites-available/moodle /etc/nginx/sites-enabled/moodle

RUN systemctl mask systemd-sysctl.service systemd-udevd.service systemd-udevd-kernel.socket \
      systemd-udevd-control.socket systemd-modules-load.service getty.target console-getty.service \
      systemd-remount-fs.service sys-kernel-debug.mount sys-kernel-tracing.mount \
 && systemctl enable ssh.socket

STOPSIGNAL SIGRTMIN+3
CMD ["/sbin/init"]
