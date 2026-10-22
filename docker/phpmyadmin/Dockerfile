# Official phpMyAdmin 5.2 image rebuilt with the patched PHP runtime.
FROM phpmyadmin:5.2.3-apache@sha256:62a50b1419fe55b9b4c3cd6f5aed03a18f8f5b4a236f24735d76400bea75a5ff

# Some architectures can have delayed upstream rebuilds: fail rather than run an old PHP.
RUN php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3 || version_compare(PHP_VERSION, "8.3.36", "<")) { fwrite(STDERR, "Patched PHP 8.3.36+ required\n"); exit(1); } echo "PHP ", PHP_VERSION, " verified\n";' \
    && apache2ctl -t
