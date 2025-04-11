<template>
  <LayoutsCompanyCard
    title="Team & Management"
    icon="fa-users"
  >
    <!-- Actions slot -->
    <template #actions>
      <OButton
        @click="refreshTeamHierarchy"
        :loading="teamPending"
        label="Refresh Team"
        type="secondary"
        icon="fa-sync"
      />
    </template>

    <!-- Loading slot -->
    <template #loading>
      <OAlert
        v-if="teamPending"
        message="Loading company team hierarchy..."
        title="Please wait"
        description="Team hierarchy data will be displayed here once available."
        icon="fa-spinner fa-spin"
        color="blue"
      />
    </template>

    <!-- Empty state -->
    <Card v-if="!hasTeamData && !teamPending">
      <div class="text-center py-8">
        <div class="text-5xl text-slate-300 mb-4">
          <i class="fa fa-users"></i>
        </div>
        <h3 class="text-xl font-semibold mb-2">No Team Data Available</h3>
        <p class="text-slate-500 mb-6">
          Fetch team hierarchy for this company to see management structure
        </p>
      </div>
    </Card>

    <!-- Main content -->
    <div
      v-if="hasTeamData"
      class="space-y-6"
    >
      <Card>
        <div class="flex items-center gap-2 text-primary mb-4">
          <i class="fa fa-sitemap"></i>
          <span>Management Hierarchy</span>
        </div>
          <ClientOnly>

        <div class="h-[600px] w-full">
          <VueFlow
            :nodes="layoutedNodes"
            :edges="edges"
            :default-viewport="{ x: 0, y: 0, zoom: 1 }"
            :draggable="false"
            @init="() => {
              isInitialized = true
              applyLayoutAndFitView()
            }"
          >
            <template #node-team-member="props">
              <TeamMemberNode v-bind="props" />
            </template>
          </VueFlow>
          </div>
        </ClientOnly>
      </Card>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert, OButton } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'
import TeamMemberCard from '~/components/TeamMemberCard.vue'
import { useAgentStore } from '~/stores/agent'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import TeamMemberNode from '~/components/nodes/TeamMemberNode.vue'
import dagre from 'dagre'
import { nextTick, ref, watch } from 'vue'

// Set page metadata
useHead({
  title: 'Mint - Team & Management',
  meta: [{ name: 'description', content: 'Company Team and Management Information' }],
})

const { companyName } = useCompanyData()
const { findTeamHierarchy } = useTeamAgent()
const agentStore = useAgentStore()
const teamPending = computed(() => agentStore.getPendingState('team'))
const companyStore = useCompanyStore()
const { fitView } = useVueFlow()

// Computed properties for data access
const company = computed(() => {
  return companyStore.getCompanyByName(companyName.value)
})

const hasTeamData = computed(() => {
  return !!company.value?.team && company.value.team.length > 0
})

// Generate nodes and edges from team hierarchy
const nodes = computed(() => {
  if (!company.value?.team) return []
  
  const generateNodes = (members: any[], level = 0, parentId = null) => {
    let nodes = []
    
    for (const member of members) {
      const nodeId = `${member.position}-${member.firstName}-${member.lastName}`
      const node = {
        id: nodeId,
        type: 'team-member',
        position: { x: 0, y: 0 }, // Position will be set by dagre
        data: {
          position: member.position,
          firstName: member.firstName,
          lastName: member.lastName
        }
      }
      
      nodes.push(node)
      
      if (member.subordinates && member.subordinates.length > 0) {
        const childNodes = generateNodes(member.subordinates, level + 1, nodeId)
        nodes = nodes.concat(childNodes)
      }
    }
    
    return nodes
  }
  
  return generateNodes(company.value.team)
})

const edges = computed(() => {
  if (!company.value?.team) return []
  
  const generateEdges = (members: any[]) => {
    let edges = []
    
    for (const member of members) {
      const sourceId = `${member.position}-${member.firstName}-${member.lastName}`
      
      if (member.subordinates && member.subordinates.length > 0) {
        for (const subordinate of member.subordinates) {
          const targetId = `${subordinate.position}-${subordinate.firstName}-${subordinate.lastName}`
          edges.push({
            id: `${sourceId}-${targetId}`,
            source: sourceId,
            target: targetId,
            type: 'smoothstep'
          })
        }
        
        const childEdges = generateEdges(member.subordinates)
        edges = edges.concat(childEdges)
      }
    }
    
    return edges
  }
  
  return generateEdges(company.value.team)
})

// Apply dagre layout
const applyLayout = () => {
  const g = new dagre.graphlib.Graph()
  g.setGraph({
    rankdir: 'TB',
    align: 'UL',
    nodesep: 50,
    ranksep: 100,
    marginx: 50,
    marginy: 50
  })
  g.setDefaultEdgeLabel(() => ({}))

  // Add nodes to the graph
  nodes.value.forEach((node) => {
    g.setNode(node.id, { width: 200, height: 100 })
  })

  // Add edges to the graph
  edges.value.forEach((edge) => {
    g.setEdge(edge.source, edge.target)
  })

  // Calculate the layout
  dagre.layout(g)

  // Update node positions
  const updatedNodes = nodes.value.map((node) => {
    const nodeWithPosition = g.node(node.id)
    return {
      ...node,
      position: {
        x: nodeWithPosition.x - 100, // Center the node
        y: nodeWithPosition.y - 50
      }
    }
  })

  return updatedNodes
}

// Watch for changes in nodes and edges to apply layout
const layoutedNodes = ref([])
const isInitialized = ref(false)

const applyLayoutAndFitView = () => {
  if (nodes.value.length > 0 && edges.value.length > 0) {
    layoutedNodes.value = applyLayout()
    if (isInitialized.value) {
      nextTick(() => {
        fitView()
      })
    }
  }
}

watch([nodes, edges], applyLayoutAndFitView, { immediate: true })

// Generate or refresh team hierarchy data
const refreshTeamHierarchy = async () => {
  await findTeamHierarchy(companyName.value)
}
</script>
