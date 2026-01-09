#!/usr/bin/env python3
"""
Script to generate gRPC Python stubs from .proto files
"""
import os
import re
import subprocess
import sys
from pathlib import Path

def generate_protos():
    """Generate Python gRPC stubs from .proto files"""
    
    project_root = Path(__file__).parent.parent
    protos_dir = project_root / "protos"
    output_dir = project_root / "app" / "grpc_generated"
    
    # Create output directory
    output_dir.mkdir(parents=True, exist_ok=True)
    
    # Build protoc command
    cmd = [
        sys.executable, "-m", "grpc_tools.protoc",
        f"-I{protos_dir}",
        f"--python_out={output_dir}",
        f"--grpc_python_out={output_dir}",
        f"--pyi_out={output_dir}",
    ]
    
    # Add all proto files
    proto_files = list(protos_dir.glob("*.proto"))
    cmd.extend([str(p) for p in proto_files])
    
    print(f"Generating gRPC stubs...")
    
    # Run protoc
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        print("✅ Successfully generated gRPC stubs")
        
        # Fix imports in ALL generated files
        fix_all_imports(output_dir)
        
        # Create __init__.py
        (output_dir / "__init__.py").write_text('# Generated gRPC stubs\n')
        
        return True
        
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed: {e.stderr}")
        return False

def fix_all_imports(directory: Path):
    """Fix relative imports in all generated Python files"""
    for file_path in directory.glob("*.py"):
        fix_file_imports(file_path)

def fix_file_imports(file_path: Path):
    """Fix imports in a single generated file"""
    content = file_path.read_text()
    
    # Pattern to match problematic imports
    patterns = [
        (r'import (\w+)_pb2 as \1__pb2', r'from . import \1_pb2 as \1__pb2'),
        (r'from (\w+) import (\w+)_pb2 as \1__pb2', r'from . import \2_pb2 as \1__pb2'),
    ]
    
    modified = False
    for pattern, replacement in patterns:
        new_content = re.sub(pattern, replacement, content)
        if new_content != content:
            content = new_content
            modified = True
    
    # Also fix grpc module imports if needed
    if 'grpc' in content and 'grpc_tools' not in content:
        content = content.replace(
            'import grpc',
            'import grpc\nfrom grpc_tools import protoc'
        )
    
    if modified:
        file_path.write_text(content)
        print(f"  Fixed imports in {file_path.name}")

if __name__ == "__main__":
    success = generate_protos()
    sys.exit(0 if success else 1)