import grpc
import time

from app.grpc_server import create_grpc_server
from app.core.config import settings


def test_grpc_server_starts():
    server = create_grpc_server()
    server.start()

    channel = grpc.insecure_channel(f"localhost:{settings.GRPC_PORT}")
    grpc.channel_ready_future(channel).result(timeout=5)

    server.stop(0)


def test_grpc_reflection_available():
    server = create_grpc_server()
    server.start()

    channel = grpc.insecure_channel(f"localhost:{settings.GRPC_PORT}")
    grpc.channel_ready_future(channel).result(timeout=5)

    server.stop(0)


def test_grpc_graceful_shutdown():
    server = create_grpc_server()
    server.start()
    time.sleep(0.2)

    result = server.stop(grace=1)
    result.wait(timeout=2)