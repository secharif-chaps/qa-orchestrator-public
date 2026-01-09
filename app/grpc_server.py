import grpc
from concurrent import futures
from grpc_reflection.v1alpha import reflection
import logging

from app.core.config import settings
from app.grpc_generated import organization_pb2
from app.grpc_generated import organization_pb2_grpc
from app.grpc_services.organization import OrganizationService

logger = logging.getLogger(__name__)


def create_grpc_server() -> grpc.Server:
    server = grpc.server(
        futures.ThreadPoolExecutor(max_workers=10)
    )

    organization_pb2_grpc.add_OrganizationServiceServicer_to_server(
        OrganizationService(),
        server,
    )

    SERVICE_NAMES = (
        organization_pb2.DESCRIPTOR.services_by_name["OrganizationService"].full_name,
        reflection.SERVICE_NAME,
    )

    reflection.enable_server_reflection(SERVICE_NAMES, server)

    server.add_insecure_port(f"[::]:{settings.GRPC_PORT}")

    logger.info(
        "🚀 gRPC server listening on port %s",
        settings.GRPC_PORT,
    )

    return server