FROM php:8.2-apache

# Cài đặt các thư viện hệ thống cần thiết cho Ghostscript, Tesseract OCR và PHP Extensions
RUN apt-get update && apt-get install -y \
    ghostscript \
    tesseract-ocr \
    tesseract-ocr-vie \
    tesseract-ocr-eng \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Cấu hình và cài đặt PHP extensions (GD, ZIP)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip

# Bật Apache mod_rewrite
RUN a2enmod rewrite

# Cài đặt Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Copy file cấu hình PHP tùy chỉnh
COPY docker-php.ini /usr/local/etc/php/conf.d/custom.ini

# Cấp quyền cho thư mục root của apache
RUN chown -R www-data:www-data /var/www/html

# ── Tối ưu CPU cho Tesseract OCR ──
# OMP_NUM_THREADS=0 = Tesseract dùng TẤT CẢ CPU cores có sẵn
ENV OMP_NUM_THREADS=0
# Tắt giới hạn thread mặc định của OpenMP
ENV OMP_THREAD_LIMIT=0

# Mở port 80
EXPOSE 80
