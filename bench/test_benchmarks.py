import os
from pathlib import Path
import subprocess

import pytest


ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY", "php")
TASKS = [
    "analyze",
    "service_map_load",
    "container_class_id",
    "container_map_id",
    "container_flag_throw",
    "container_flag_null",
    "container_flag_builder_0",
    "container_flag_union",
    "container_unknown",
    "root_node",
    "config_children",
    "config_scalar_node",
    "config_node_literal",
    "config_prototype",
    "config_proto_shortcut",
    "config_validate",
    "config_end_tracked",
    "config_end_builder",
    "config_end_node",
    "config_chain_full",
    "hook_root_scalar",
    "hook_root_array",
]


@pytest.mark.parametrize("task", TASKS)
@pytest.mark.benchmark(max_rounds=10)
def test_benchmarks(benchmark, task):
    command = [PHP, "bench/run-local.php", f"--task={task}", "--iterations=20000"]
    benchmark(
        subprocess.run,
        command,
        cwd=ROOT,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        timeout=120,
    )
