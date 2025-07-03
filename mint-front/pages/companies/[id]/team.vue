<template>
  <LayoutsCompanyCard :title="$t('team.title')" icon="fa-users">
    <div class="flex flex-col gap-4">

    <!-- Empty state -->
    <Card v-if="!hasTeamData && !teamPending">
      <div class="text-center py-8">
        <div class="text-5xl text-slate-300 mb-4">
          <i class="fa fa-users"></i>
        </div>
        <h3 class="text-xl font-semibold mb-2">{{ $t('team.noData.title') }}</h3>
        <p class="text-slate-500 mb-6">
          {{ $t('team.noData.description') }}
        </p>
      </div>
    </Card>

    <!-- Main content -->
    <div v-if="hasTeamData" class="space-y-6">
      <Card>
        <div class="flex items-center gap-2 text-primary mb-4">
          <i class="fa fa-sitemap"></i>
          <span>{{ $t('team.hierarchy.title') }}</span>
        </div>
        <ClientOnly>
          <div class="h-[500px] w-full relative">
            <div class="absolute top-4 right-4 z-50">
              <OButton @click="doScreenshot" type="secondary" icon="fa-camera" />
            </div>

            <VueFlow
              :nodes="layoutedNodes"
              :edges="edges"
              :default-viewport="{ x: 0, y: 0, zoom: 1 }"
              :draggable="false"
              @init="
                () => {
                  isInitialized = true
                  applyLayoutAndFitView()
                }
              "
              class="bg-bg3 rounded-lg"
            >
              <template #node-team-member="props">
                <TeamMemberNode
                  v-bind="{
                    ...props,
                    data: { ...props.data, selected: selectedNode === props.data }
                  }"
                  @click="openTeamMemberCard(props.data)"
                />
              </template>
              <Background :color="'#CBD5E1'" :size="4" :gap="60" />

              <Panel
                position="top-left"
                v-if="selectedNode"
                class="bg-bg1 rounded-lg max-w-[300px] ring-2"
                :class="{
                  'ring-orange-400': selectedNode.level > 1,
                  'ring-purple-600': selectedNode.level <= 1
                }"
              >
                <div v-if="selectedNode" class="p-0.5">
                  <div
                    class="flex items-center gap-3 p-2 rounded-lg"
                    :class="[selectedNode.level > 1 ? 'bg-orange-50' : 'bg-purple-100']"
                  >
                    <div
                      class="min-w-12 grow-0 h-12 rounded-full flex items-center justify-center"
                      :class="[
                        selectedNode.level > 1
                          ? 'bg-orange-200 text-orange-600'
                          : 'bg-purple-200 text-purple-600'
                      ]"
                    >
                      <i class="fa fa-user text-xl"></i>
                    </div>
                    <div>
                      <h3 class="font-semibold">
                        {{ selectedNode.firstName }} {{ selectedNode.lastName }}
                      </h3>
                      <p class="text-sm">{{ selectedNode.position }}</p>
                    </div>
                    <div class="ml-auto">
                      <OButton
                        @click="selectedNode = null"
                        type="secondary"
                        icon="fa-times"
                        class="rounded-full"
                        :color="selectedNode.level > 1 ? 'orange' : 'purple'"
                      />
                    </div>
                  </div>

                  <div class="space-y-3 p-2">
                    <a
                      :href="getMockedLinkedInUrl(selectedNode)"
                      target="_blank"
                      class="flex items-center gap-2 text-sm text-primary hover:underline"
                    >
                      <i class="fab fa-linkedin"></i>
                      <span>{{ $t('team.hierarchy.viewLinkedIn') }}</span>
                    </a>

                    <div class="flex items-start gap-2 text-sm text-slate-600">
                      <i class="fa fa-map-marker-alt mt-1"></i>
                      <span>{{ getMockedAddress(selectedNode) }}</span>
                    </div>
                  </div>
                </div>
              </Panel>
            </VueFlow>
          </div>
        </ClientOnly>
      </Card>
    </div>
  </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
import { Background } from '@vue-flow/background'
import { Panel, VueFlow, useVueFlow } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import { nextTick, ref, watch } from 'vue'
import TeamMemberNode from '~/components/nodes/TeamMemberNode.vue'
import TaskState from '~/components/TaskState.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('team.title')}`,
  meta: [{ name: 'description', content: t('team.title') }]
})

const { company, companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})

const teamPending = computed(() => {
  if (!companyId.value) {
    return false
  }
  return company.value?.tasks?.some(
    (task: { type: string; status: string }) => task.type === 'team' && (task.status === 'pending' || task.status === 'running')
  )
})

const { fitView, vueFlowRef } = useVueFlow()

// Computed properties for data access
const hasTeamData = computed(() => {
  return !!company.value?.team && company.value.team.length > 0
})

// Generate nodes and edges from team hierarchy
const nodes = computed(() => {
  if (!company.value?.team) return []

  const generateNodes = (members: any[], level = 0, parentId = null): any[] => {
    let nodes: any[] = []

    for (const member of members) {
      const nodeId = `${member.position}-${member.firstName}-${member.lastName}`
      const node = {
        id: nodeId,
        type: 'team-member',
        position: { x: 0, y: 0 }, // Position will be set by dagre
        data: {
          position: member.position,
          firstName: member.firstName,
          lastName: member.lastName,
          level: level
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

  const generateEdges = (members: any[], level = 0): any[] => {
    let edges: any[] = []

    for (const member of members) {
      const sourceId = `${member.position}-${member.firstName}-${member.lastName}`

      if (member.subordinates && member.subordinates.length > 0) {
        for (const subordinate of member.subordinates) {
          const targetId = `${subordinate.position}-${subordinate.firstName}-${subordinate.lastName}`
          edges.push({
            id: `${sourceId}-${targetId}`,
            source: sourceId,
            target: targetId,
            data: {
              level: level + 1
            },
            style: {
              stroke: level === 0 ? '#9333EA' : 'oklch(0.75 0.183 55.934)',
              strokeWidth: 4,
              strokeOpacity: 1
            }
          })
        }

        const childEdges = generateEdges(member.subordinates, level + 1)
        edges = edges.concat(childEdges)
      }
    }

    return edges
  }

  return generateEdges(company.value.team)
})

// Apply dagre layout
const applyLayout = () => {
  
  const NODE_WIDTH = 300
  const NODE_HEIGHT = 100
  const HORIZONTAL_PADDING = 30
  const VERTICAL_SPACING = 100


  // First, let's create a map of nodes and their subordinates
  const nodeMap = new Map<string, any>()
  nodes.value.forEach(node => {
    nodeMap.set(node.id, {
      ...node,
      subordinateIds: [],
      position: { x: 0, y: 0 }
    })
  })

  // Fill in subordinates information
  edges.value.forEach(edge => {
    const parentNode = nodeMap.get(edge.source)
    if (parentNode) {
      parentNode.subordinateIds.push(edge.target)
    }
  })

  // Find leaf nodes (nodes without subordinates)
  const leafNodes = Array.from(nodeMap.values()).filter(node => node.subordinateIds.length === 0)

  // Position leaf nodes side by side
  let currentX = 0
  leafNodes.forEach(node => {
    node.position = {
      x: currentX,
      y: 0
    }
    currentX += NODE_WIDTH + HORIZONTAL_PADDING
  })

  // Function to get max level in the hierarchy
  const getMaxLevel = () => {
    return Math.max(...Array.from(nodeMap.values()).map(node => node.data.level))
  }

  // Process each level from bottom to top
  const maxLevel = getMaxLevel()
  for (let level = maxLevel - 1; level >= 0; level--) {
    const nodesAtLevel = Array.from(nodeMap.values()).filter(node => node.data.level === level)

    nodesAtLevel.forEach(node => {
      if (node.subordinateIds.length > 0) {
        // Get positions of all subordinates
        const subordinatePositions = node.subordinateIds.map(id => nodeMap.get(id).position)

        // Calculate the center position based on subordinates
        const minX = Math.min(...subordinatePositions.map(pos => pos.x))
        const maxX = Math.max(...subordinatePositions.map(pos => pos.x))
        const centerX = minX + (maxX - minX) / 2

        // Position the node above its subordinates
        node.position = {
          x: centerX,
          y: -(maxLevel - level) * (NODE_HEIGHT + VERTICAL_SPACING)
        }
      }
    })
  }

  // Convert positions back to the format expected by VueFlow
  const updatedNodes = nodes.value.map(node => {
    const nodeWithPosition = nodeMap.get(node.id)
    return {
      ...node,
      position: {
        x: nodeWithPosition.position.x - NODE_WIDTH / 2, // Center the node by subtracting half its width
        y: nodeWithPosition.position.y
      }
    }
  })

  return updatedNodes
}

// Watch for changes in nodes and edges to apply layout
const layoutedNodes = ref([])
const isInitialized = ref(false)

const applyLayoutAndFitView = () => {

  if (nodes.value.length > 0 ) {
    
    layoutedNodes.value = applyLayout()
    if (isInitialized.value) {
      nextTick(() => {
        fitView()
      })
    }
  }
}

watch([nodes, edges], applyLayoutAndFitView, { immediate: true })

const selectedNode = ref<any>(null)
const openTeamMemberCard = (data: any) => {
  selectedNode.value = data
}

const { capture } = useScreenshot()

function doScreenshot() {
  if (!vueFlowRef.value) {
    console.warn('VueFlow element not found')
    return
  }

  capture(vueFlowRef.value, { shouldDownload: true })
}

// Helper functions for mocked data
const getMockedLinkedInUrl = (node: any) => {
  if (!node) return '#'
  const fullName = `${node.firstName}${node.lastName}`.toLowerCase().replace(/[^a-z0-9]/g, '')
  return `https://www.linkedin.com/in/${fullName}`
}

const getMockedAddress = (node: any) => {
  // Return a random address based on the node's data to keep it consistent
  const addresses = [
    '6 Rue Moyenne, 18000 Bourges',
    '12 Avenue des Champs-Élysées, 75008 Paris',
    '8 Place Bellecour, 69002 Lyon',
    '15 Rue de la République, 13001 Marseille',
    '3 Rue du Commerce, 44000 Nantes'
  ]

  // Use a deterministic way to select an address based on the node's name
  const index = (node.firstName.length + node.lastName.length) % addresses.length
  return addresses[index]
}
</script>
