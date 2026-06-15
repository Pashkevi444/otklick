# PostgreSQL 16 + pgvector из официального образа (library/postgres покрывается
# зеркалом mirror.gcr.io). Альтернатива образу pgvector/pgvector, который не
# тянется при лимите анонимных пуллов Docker Hub.
FROM postgres:16

RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-16-pgvector \
    && rm -rf /var/lib/apt/lists/*
