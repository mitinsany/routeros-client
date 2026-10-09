FROM debian:bookworm-slim AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends autoconf bison build-essential ca-certificates curl libxml2-dev re2c \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://museum.php.net/php5/php-5.4.45.tar.gz -o /tmp/php.tar.gz \
    && tar -xzf /tmp/php.tar.gz -C /tmp \
    && cd /tmp/php-5.4.45 \
    && ./configure --disable-all --disable-cgi --enable-cli --enable-dom --enable-filter --enable-json --enable-libxml --enable-phar --enable-simplexml --enable-tokenizer --enable-xml --enable-xmlwriter --with-iconv --with-libxml-dir=/usr \
    && make -j"$(nproc)" \
    && make install

FROM debian:bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends libxml2 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=build /usr/local/bin/php /usr/local/bin/php
COPY --from=build /usr/local/lib/php /usr/local/lib/php
WORKDIR /app
