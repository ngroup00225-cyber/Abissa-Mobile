"""Compatibility entry point: use the verified current Play Release builder."""
from pathlib import Path
import runpy

if __name__ == '__main__':
    runpy.run_path(str(Path(__file__).with_name('build-play-release-20261011.py')), run_name='__main__')
