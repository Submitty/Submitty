FROM postgres:12

COPY ./setup-contrib.sql /docker-entrypoint-initdb.d/1-setup.sql
COPY ./contributors.sql /docker-entrypoint-initdb.d/2-data.sql
COPY ./setup-users.sql /docker-entrypoint-initdb.d/3-users.sql

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
      grep \
      libseccomp-dev \
      libseccomp2 \
      procps \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
  && apt-get install -y --no-install-recommends wget gnupg \
  && echo "deb http://apt.llvm.org/buster/ llvm-toolchain-buster-7 main" >> /etc/apt/sources.list \
  && echo "deb-src http://apt.llvm.org/buster/ llvm-toolchain-buster-7 main" >> /etc/apt/sources.list \
  && wget -O - http://apt.llvm.org/llvm-snapshot.gpg.key|apt-key add - \
  && apt-get update \
  && apt-get -y --no-install-recommends install \
    clang-7 \
  && apt-get purge -y --auto-remove wget gnupg \
  && rm -rf /var/lib/apt/lists/* \
  && ln -s /usr/bin/clang-7 /usr/bin/clang \
  && ln -s /usr/bin/clang++-7 /usr/bin/clang++
  