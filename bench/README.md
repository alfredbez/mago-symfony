# Benchmarks

Deterministic Symfony container, Config `TreeBuilder`, and `TreeBuilderRootNodeHook`
benchmarks isolated from `tests/fixtures/`.

Regenerate fixtures and the 256-entry container map with:

```bash
python3 bench/generate-fixtures.py
```

Run local measurements with PHP 8.4 and Mago 1.60.2+:

```bash
docker run --rm \
  -v "$PWD:/work" \
  -v /path/to/mago:/opt/mago:ro \
  -w /work php:8.4-cli \
  php bench/run-local.php --runs=7 --json=bench/baseline.json
```

Compare a subsequent run against the baseline:

```bash
docker run --rm \
  -v "$PWD:/work" \
  -v /path/to/mago:/opt/mago:ro \
  -w /work php:8.4-cli \
  php bench/run-local.php --runs=7 --compare=bench/baseline.json --json=bench/after.json
```

`analyze` runs `mago --config bench/mago.toml analyze` end-to-end with one analyzer
thread and one extension worker and fails if any fixture reports an issue. Provider
and hook workloads run in-process with pre-seeded SDK metadata caches.

Run the CodSpeed suite locally with `pytest-codspeed`:

```bash
python3 -m pip install -r bench/requirements.txt
python3 -B -m pytest -p no:cacheprovider bench/test_benchmarks.py --codspeed --codspeed-mode=walltime
```
