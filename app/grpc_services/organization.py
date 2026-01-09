from app.grpc_generated import (
    organization_pb2,
    organization_pb2_grpc,
)


class OrganizationService(
    organization_pb2_grpc.OrganizationServiceServicer
):
    def GetOrganizationContext(self, request, context):
        return organization_pb2.GetOrganizationContextResponse(
            organization_id=request.organization_id,
            name="Demo Org",
            enabled_modules=["billing", "users"],
        )

    def IsModuleEnabled(self, request, context):
        enabled = request.module_name in {"billing", "users"}
        return organization_pb2.IsModuleEnabledResponse(enabled=enabled)