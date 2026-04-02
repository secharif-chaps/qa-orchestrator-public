"""Company analysis runner: orchestrates graph execution with persistence and SSE."""

import asyncio
from datetime import UTC, datetime

from sqlalchemy.orm import Session

from app.agents.config import (
    GLOBAL_ANALYSIS_TIMEOUT_SECONDS,
    INPUT_PRICE_PER_MILLION,
    OUTPUT_PRICE_PER_MILLION,
)
from app.agents.nodes.base import run_agent
from app.agents.state import AgentResult, CompanyAnalysisState
from app.core.dependencies import get_global_service_client
from app.core.logging_config import get_logger
from app.models.company import Company
from app.models.task import Task, TaskStatus
from app.services.company_section_service import write_section_data
from app.services.task_events import task_event_manager

logger = get_logger(__name__)


class CompanyAnalysisRunner:
    """Runs the LangGraph analysis graph with progressive persistence."""

    async def run(
        self,
        db: Session,
        company_id: int,
        company_name: str,
        website: str,
        organization_id: str,
        owner_id: str,
    ) -> None:
        """Run full company analysis via the LangGraph graph.

        Streams events, persists results per-agent, updates task status,
        and broadcasts SSE updates.
        """
        logger.info(
            "Starting company analysis",
            extra={"company_id": company_id, "company_name": company_name},
        )

        # Build task lookup: agent_name -> Task
        def _init_tasks() -> dict[str, Task]:
            tasks = db.query(Task).filter(Task.company_id == company_id).all()
            task_map: dict[str, Task] = {}
            for task in tasks:
                task_map[task.type.value] = task
                task.status = TaskStatus.RUNNING
                task.updated_at = datetime.now(UTC)
            db.commit()
            return task_map

        task_map = await asyncio.to_thread(_init_tasks)

        # Broadcast all tasks as running
        for agent_name, task in task_map.items():
            await task_event_manager.broadcast_task_update(
                user_id=owner_id,
                company_id=company_id,
                task_id=task.id,
                status="running",
                task_type=agent_name,
            )

        # Build initial state
        initial_state: CompanyAnalysisState = {
            "company_id": company_id,
            "company_name": company_name,
            "website": website,
            "organization_id": organization_id,
            "owner_id": owner_id,
            "country_code": None,
            "company_brief": None,
            "enrichment_data": {},
            "agents_to_run": [],
            "agent_results": [],
            "quality_issues": [],
            "agents_to_retry": [],
            "retry_counts": {},
            "total_tokens": 0,
            "total_cost": 0.0,
        }

        try:
            # Lazy import to avoid circular deps at module level
            from app.agents.graph import get_analysis_graph

            graph = await get_analysis_graph()
            config = {"configurable": {"thread_id": f"company-{company_id}"}}

            async def _stream_graph() -> None:
                """Stream events for progressive persistence."""
                async for event in graph.astream_events(initial_state, config=config, version="v2"):
                    if event["event"] == "on_chain_end" and event["name"] == "data_collector":
                        enrichment_output = event.get("data", {}).get("output", {})
                        enrichment_keys = list(enrichment_output.get("enrichment_data", {}).keys())
                        logger.info(
                            "Data collector completed",
                            extra={
                                "company_id": company_id,
                                "enrichment_sources": enrichment_keys,
                            },
                        )
                    elif event["event"] == "on_chain_end" and event["name"].startswith("agent_"):
                        agent_name = event["name"].replace("agent_", "")
                        result = self._extract_result(event, agent_name)
                        if result:
                            await self._persist_agent_result(db, company_id, agent_name, result)
                            task = task_map.get(agent_name)
                            if task:
                                await self._update_task_status(db, task, result)
                                await self._broadcast_result(owner_id, company_id, task, result)
                        else:
                            logger.warning(
                                "No AgentResult extracted from stream event",
                                extra={
                                    "company_id": company_id,
                                    "agent_name": agent_name,
                                    "event_keys": list(event.get("data", {}).keys()),
                                },
                            )

            await asyncio.wait_for(
                _stream_graph(),
                timeout=GLOBAL_ANALYSIS_TIMEOUT_SECONDS,
            )

            # Broadcast completion
            await self._broadcast_completion(db, owner_id, company_id, company_name, organization_id, task_map)

        except TimeoutError:
            timeout_msg = f"Analysis timed out after {GLOBAL_ANALYSIS_TIMEOUT_SECONDS}s"
            logger.error(
                timeout_msg,
                extra={"company_id": company_id},
            )
            await self._mark_tasks_errored(db, task_map, timeout_msg)
            for agent_name, task in task_map.items():
                if task.status == TaskStatus.ERROR:
                    await task_event_manager.broadcast_task_update(
                        user_id=owner_id,
                        company_id=company_id,
                        task_id=task.id,
                        status="error",
                        task_type=agent_name,
                        error=timeout_msg,
                    )
            await self._broadcast_completion(db, owner_id, company_id, company_name, organization_id, task_map)

        except Exception as e:
            logger.error(
                f"Company analysis failed: {e}",
                exc_info=True,
                extra={"company_id": company_id},
            )
            await self._mark_tasks_errored(db, task_map, str(e))
            # Broadcast error for each task
            for agent_name, task in task_map.items():
                if task.status == TaskStatus.ERROR:
                    await task_event_manager.broadcast_task_update(
                        user_id=owner_id,
                        company_id=company_id,
                        task_id=task.id,
                        status="error",
                        task_type=agent_name,
                        error=str(e),
                    )
            await self._broadcast_completion(db, owner_id, company_id, company_name, organization_id, task_map)

    async def run_single_agent(
        self,
        db: Session,
        task: Task,
        company: Company,
    ) -> None:
        """Run a single agent outside the graph (for individual task restart)."""
        agent_name = task.type.value
        logger.info(
            f"Restarting single agent: {agent_name}",
            extra={"task_id": task.id, "company_id": company.id},
        )

        def _mark_running() -> None:
            task.status = TaskStatus.RUNNING
            task.error = None
            task.error_details = None
            task.updated_at = datetime.now(UTC)
            db.commit()

        await asyncio.to_thread(_mark_running)

        await task_event_manager.broadcast_task_update(
            user_id=company.owner_id,
            company_id=company.id,
            task_id=task.id,
            status="running",
            task_type=agent_name,
        )

        try:
            # corporate_structure and sanctions use WorldCheck data, not web search
            if agent_name in ("corporate_structure", "sanctions"):
                node_fn = self._get_worldcheck_agent(agent_name)
                state = self._build_agent_state(company, agent_name)
                state_result = await node_fn(state)
                result = state_result["agent_results"][0]
            else:
                # Load enrichment sources from DB for agents that support them
                enrichment_sources = None
                company_id_for_tool = None
                if agent_name in ("profile", "team"):
                    from app.models.company_enrichment import CompanyEnrichment

                    enrichments = (
                        db.query(CompanyEnrichment)
                        .filter(
                            CompanyEnrichment.company_id == company.id,
                            CompanyEnrichment.status == "success",
                        )
                        .all()
                    )
                    if enrichments:
                        enrichment_sources = [e.source for e in enrichments]
                        company_id_for_tool = company.id

                result = await run_agent(
                    agent_name=agent_name,
                    company_name=company.name,
                    website=company.website,
                    enrichment_sources=enrichment_sources,
                    company_id=company_id_for_tool,
                )

            await self._persist_agent_result(db, company.id, agent_name, result)
            await self._update_task_status(db, task, result)
            await self._broadcast_result(company.owner_id, company.id, task, result)

        except Exception as e:
            logger.error(f"Single agent {agent_name} failed: {e}", exc_info=True)
            error_msg = str(e)

            def _mark_error() -> None:
                task.status = TaskStatus.ERROR
                task.error = error_msg
                task.error_details = {
                    "error_type": "agent_execution_error",
                    "message": error_msg,
                    "is_recoverable": True,
                }
                task.updated_at = datetime.now(UTC)
                db.commit()

            await asyncio.to_thread(_mark_error)

            await task_event_manager.broadcast_task_update(
                user_id=company.owner_id,
                company_id=company.id,
                task_id=task.id,
                status="error",
                task_type=agent_name,
                error=str(e),
            )

    @staticmethod
    def _build_agent_state(company: Company, agent_name: str) -> CompanyAnalysisState:
        """Build a minimal CompanyAnalysisState for running a single WorldCheck-based agent."""
        return {
            "company_id": company.id,
            "company_name": company.name,
            "website": company.website,
            "organization_id": company.organization_id or "",
            "owner_id": company.owner_id or "",
            "country_code": None,
            "company_brief": None,
            "agents_to_run": [agent_name],
            "agent_results": [],
            "quality_issues": [],
            "agents_to_retry": [],
            "retry_counts": {},
            "total_tokens": 0,
            "total_cost": 0.0,
        }

    @staticmethod
    def _get_worldcheck_agent(agent_name: str):
        """Return the node function for a WorldCheck-based agent."""
        if agent_name == "corporate_structure":
            from app.agents.nodes.corporate_structure import run_corporate_structure_agent

            return run_corporate_structure_agent
        elif agent_name == "sanctions":
            from app.agents.nodes.sanctions import run_sanctions_agent

            return run_sanctions_agent
        raise ValueError(f"Unknown WorldCheck agent: {agent_name}")

    def _extract_result(self, event: dict, agent_name: str) -> AgentResult | None:
        """Extract AgentResult from a stream event."""
        try:
            output = event.get("data", {}).get("output", {})
            results = output.get("agent_results", [])
            for result in results:
                if result.get("agent_name") == agent_name:
                    return result
        except Exception:
            pass
        return None

    async def _persist_agent_result(
        self,
        db: Session,
        company_id: int,
        agent_name: str,
        result: AgentResult,
    ) -> None:
        """Write agent result to normalized section tables."""
        if result["status"] != "success" or not result["data"]:
            return

        try:
            await asyncio.to_thread(write_section_data, db, company_id, agent_name, result["data"])
            logger.info(
                f"Persisted {agent_name} data",
                extra={"company_id": company_id, "agent_name": agent_name},
            )
        except Exception as e:
            logger.error(
                f"Failed to persist {agent_name} data: {e}",
                exc_info=True,
                extra={"company_id": company_id},
            )

    async def _update_task_status(self, db: Session, task: Task, result: AgentResult) -> None:
        """Update task status based on agent result."""

        def _update() -> None:
            if result["status"] == "success":
                task.status = TaskStatus.SUCCEEDED
                task.error = None
                task.error_details = None
            else:
                task.status = TaskStatus.ERROR
                task.error = result.get("error", "Unknown error")
                task.error_details = {
                    "error_type": "agent_error",
                    "message": result.get("error", "Unknown error"),
                    "is_recoverable": True,
                    "agent_name": result["agent_name"],
                }

            # Accumulate token/cost info (don't overwrite on retry)
            new_input = result["input_tokens"]
            new_output = result["output_tokens"]
            cost = (new_input / 1_000_000) * INPUT_PRICE_PER_MILLION + (
                new_output / 1_000_000
            ) * OUTPUT_PRICE_PER_MILLION
            task.input_tokens = (task.input_tokens or 0) + new_input
            task.output_tokens = (task.output_tokens or 0) + new_output
            task.total_cost = (task.total_cost or 0) + cost
            task.updated_at = datetime.now(UTC)
            db.commit()

        await asyncio.to_thread(_update)

    async def _broadcast_result(
        self,
        owner_id: str,
        company_id: int,
        task: Task,
        result: AgentResult,
    ) -> None:
        """Broadcast task update via SSE."""
        await task_event_manager.broadcast_task_update(
            user_id=owner_id,
            company_id=company_id,
            task_id=task.id,
            status=task.status.value,
            task_type=result["agent_name"],
            error=result.get("error"),
        )

    async def _broadcast_completion(
        self,
        db: Session,
        owner_id: str,
        company_id: int,
        company_name: str,
        organization_id: str,
        task_map: dict[str, Task],
    ) -> None:
        """Broadcast all_tasks_completed event."""
        success_count = sum(1 for t in task_map.values() if t.status == TaskStatus.SUCCEEDED)
        error_count = sum(1 for t in task_map.values() if t.status == TaskStatus.ERROR)

        # Look up folder_id via global-service
        try:
            client = get_global_service_client()
            folder_id = await client.get_company_folder_id(
                org_id=organization_id,
                company_id=company_id,
                user_id=owner_id,
            )
        except Exception as e:
            logger.warning("Failed to get folder_id from global-service", extra={"error": str(e)})
            folder_id = None

        await task_event_manager.broadcast_all_tasks_completed(
            user_id=owner_id,
            company_id=company_id,
            company_name=company_name,
            folder_id=folder_id,
            success_count=success_count,
            error_count=error_count,
        )

    async def _mark_tasks_errored(self, db: Session, task_map: dict[str, Task], error_msg: str) -> None:
        """Mark all still-running tasks as error."""

        def _mark() -> None:
            for task in task_map.values():
                if task.status == TaskStatus.RUNNING:
                    task.status = TaskStatus.ERROR
                    task.error = error_msg
                    task.error_details = {
                        "error_type": "pipeline_error",
                        "message": error_msg,
                        "is_recoverable": True,
                    }
                    task.updated_at = datetime.now(UTC)
            db.commit()

        await asyncio.to_thread(_mark)
