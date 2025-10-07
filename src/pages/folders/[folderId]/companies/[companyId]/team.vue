<template>
  <div class="flex flex-col gap-6">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />
    <!-- Error State -->
    <SectionErrorState
      v-else-if="company && task?.status === 'error'"
      :error-message="task.error"
      :task="task"
    />

    <!-- No Data State -->
    <div v-else-if="!company?.team">no data state</div>

    <!-- Main content -->
    <div v-if="company?.team" class="space-y-6">
      <!-- Team Header with Stats -->
      <TeamPageHeader
        :team="company?.team"
        :team-insights="company?.team_insights"
        @export="handleExport"
      />

      <!-- Team Members List -->
      <div class="bg-base-100 p-6 rounded-lg">
        <h3 class="text-lg font-semibold text-primary-light-content mb-4 flex items-center gap-2">
          <i class="fa fa-address-card"></i>
          <span>{{ $t('team.members.title', 'Team Members') }}</span>
        </h3>
        <TeamMembersList :team="company?.team" @view-in-hierarchy="scrollToMemberInHierarchy" />
      </div>

      <!-- Hierarchy Graph -->
      <div class="bg-base-100 p-6 rounded-lg">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-primary-light-content flex items-center gap-2">
            <i class="fa fa-sitemap"></i>
            <span>{{ $t('team.hierarchy.title', 'Organization Chart') }}</span>
          </h3>
          <div class="flex items-center gap-2">
            <Badge variant="info" label="Interactive" size="xs" rounded />
            <Button
              @click="doScreenshot"
              variant="ghost-primary"
              icon="fa fa-camera"
              :title="$t('team.hierarchy.screenshot', 'Take Screenshot')"
              icon-only
              size="sm"
            />
          </div>
        </div>

        <div class="h-[500px] w-full relative">
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
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg"
          >
            <template #node-team-member="props">
              <TeamMemberNode
                v-bind="{
                  ...props,
                  data: { ...props.data, selected: selectedNode === props.data },
                }"
                @click="openTeamMemberCard(props.data)"
              />
            </template>
            <Background
              :color="isDark ? 'var(--color-slate-900)' : '#CBD5E1'"
              :size="4"
              :gap="60"
            />

            <Panel
              position="top-left"
              v-if="selectedNode"
              class="bg-base-100 rounded-lg max-w-[300px] ring-4 ring-offset-2 ring-offset-bg1"
              :class="{
                'ring-orange-400 dark:ring-orange-500/20': selectedNode.level > 1,
                'ring-purple-600 dark:ring-purple-500/20': selectedNode.level <= 1,
              }"
            >
              <div v-if="selectedNode" class="p-0.5">
                <div
                  class="flex items-center gap-3 p-2 rounded-lg"
                  :class="[
                    selectedNode.level > 1
                      ? 'bg-orange-50 dark:bg-orange-900'
                      : 'bg-purple-100 dark:bg-purple-500/20',
                  ]"
                >
                  <div
                    class="min-w-12 grow-0 h-12 rounded-full flex items-center justify-center"
                    :class="[
                      selectedNode.level > 1
                        ? 'bg-orange-200 dark:bg-base-300 text-orange-600'
                        : 'bg-purple-200 dark:bg-base-300 text-purple-600',
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
                    <Button
                      @click="selectedNode = null"
                      variant="ghost-primary"
                      icon="fa fa-times"
                      icon-only
                      class="rounded-full"
                    />
                  </div>
                </div>

                <div class="space-y-3 p-2">
                  <a
                    :href="getMockedLinkedInUrl(selectedNode)"
                    target="_blank"
                    class="flex items-center gap-2 text-sm text-primary-light-content hover:underline"
                  >
                    <i class="fab fa-linkedin"></i>
                    <span>{{ $t('team.hierarchy.viewLinkedIn') }}</span>
                  </a>

                  <div class="flex items-start gap-2 text-sm">
                    <i class="fa fa-map-marker-alt mt-1"></i>
                    <span>{{ getMockedAddress(selectedNode) }}</span>
                  </div>
                </div>
              </div>
            </Panel>
          </VueFlow>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import { Background } from '@vue-flow/background'
import { Panel, VueFlow, useVueFlow } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import { computed, nextTick, ref, watch } from 'vue'

import { useTheme } from '@/composables/useTheme'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import TeamMemberNode from '@/components/company/team/TeamMemberNode.vue'
import TeamPageHeader from '@/components/company/team/TeamPageHeader.vue'
import TeamMembersList from '@/components/company/team/TeamMembersList.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import { useScreenshot } from '@/composables/useScreenshot'
import type { TeamMember } from '@/types/company'

const { isDark } = useTheme()

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'team'))

// Use the company data composable
const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
  // Poll every 5 seconds when any task is running
  refetchInterval: () => {
    const hasRunningTasks = company.value?.tasks?.some(
      (t) => t.status === 'running' || t.status === 'pending',
    )
    return hasRunningTasks ? 5000 : false
  },
}))

const { fitView, vueFlowRef } = useVueFlow()

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
          level: level,
        },
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
              level: level + 1,
            },
            style: {
              stroke: level === 0 ? '#9333EA' : 'oklch(0.75 0.183 55.934)',
              strokeWidth: 4,
              strokeOpacity: 1,
            },
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
  nodes.value.forEach((node) => {
    nodeMap.set(node.id, {
      ...node,
      subordinateIds: [],
      position: { x: 0, y: 0 },
    })
  })

  // Fill in subordinates information
  edges.value.forEach((edge) => {
    const parentNode = nodeMap.get(edge.source)
    if (parentNode) {
      parentNode.subordinateIds.push(edge.target)
    }
  })

  // Find leaf nodes (nodes without subordinates)
  const leafNodes = Array.from(nodeMap.values()).filter((node) => node.subordinateIds.length === 0)

  // Position leaf nodes side by side
  let currentX = 0
  leafNodes.forEach((node) => {
    node.position = {
      x: currentX,
      y: 0,
    }
    currentX += NODE_WIDTH + HORIZONTAL_PADDING
  })

  // Function to get max level in the hierarchy
  const getMaxLevel = () => {
    return Math.max(...Array.from(nodeMap.values()).map((node) => node.data.level))
  }

  // Process each level from bottom to top
  const maxLevel = getMaxLevel()
  for (let level = maxLevel - 1; level >= 0; level--) {
    const nodesAtLevel = Array.from(nodeMap.values()).filter((node) => node.data.level === level)

    nodesAtLevel.forEach((node) => {
      if (node.subordinateIds.length > 0) {
        // Get positions of all subordinates
        const subordinatePositions = node.subordinateIds.map((id) => nodeMap.get(id).position)

        // Calculate the center position based on subordinates
        const minX = Math.min(...subordinatePositions.map((pos) => pos.x))
        const maxX = Math.max(...subordinatePositions.map((pos) => pos.x))
        const centerX = minX + (maxX - minX) / 2

        // Position the node above its subordinates
        node.position = {
          x: centerX,
          y: -(maxLevel - level) * (NODE_HEIGHT + VERTICAL_SPACING),
        }
      }
    })
  }

  // Convert positions back to the format expected by VueFlow
  const updatedNodes = nodes.value.map((node) => {
    const nodeWithPosition = nodeMap.get(node.id)
    return {
      ...node,
      position: {
        x: nodeWithPosition.position.x - NODE_WIDTH / 2, // Center the node by subtracting half its width
        y: nodeWithPosition.position.y,
      },
    }
  })

  return updatedNodes
}

// Watch for changes in nodes and edges to apply layout
const layoutedNodes = ref([])
const isInitialized = ref(false)

const applyLayoutAndFitView = () => {
  if (nodes.value.length > 0) {
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
  return '6 Rue Moyenne, 18000 Bourges'
}

// New methods for the enhanced team page
const handleExport = () => {
  // Export team data as JSON
  const exportData = {
    company: company.value?.name,
    team: company.value?.team,
    exportDate: new Date().toISOString(),
  }

  const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${company.value?.name || 'company'}-team-${new Date().toISOString().split('T')[0]}.json`
  a.click()
  URL.revokeObjectURL(url)
}

const scrollToMemberInHierarchy = (member: TeamMember) => {
  // Find the node in the hierarchy
  const nodeId = `${member.position}-${member.firstName}-${member.lastName}`
  const node = nodes.value.find((n) => n.id === nodeId)

  if (node) {
    // Set as selected
    selectedNode.value = node.data

    // Scroll to the hierarchy section
    nextTick(() => {
      const hierarchySection = document.querySelector('.vue-flow')
      if (hierarchySection) {
        hierarchySection.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }

      // Fit view to show the selected node
      setTimeout(() => {
        fitView({ nodes: [nodeId], duration: 800, padding: 0.5 })
      }, 500)
    })
  }
}
</script>
