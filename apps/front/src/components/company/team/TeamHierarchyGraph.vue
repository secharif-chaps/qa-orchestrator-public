<template>
  <div>
    <div class="relative h-140 w-full">
      <div class="absolute top-5 right-5 z-10">
        <Tag intent="info" size="sm">{{ $t('screen.team.hierarchy.interactive') }}</Tag>
      </div>
      <div class="absolute right-5 bottom-5 z-10 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Button
            @click="resetView"
            variant="accent"
            icon="fa-arrow-rotate-left"
            :title="$t('screen.team.hierarchy.reset')"
            icon-only
            size="sm"
          />
          <Button
            @click="doScreenshot"
            icon="fa-camera"
            :title="$t('screen.team.hierarchy.screenshot')"
            icon-only
            size="sm"
          />
        </div>
      </div>
      <VueFlow
        :nodes="layoutedNodes"
        :edges="edges"
        :default-viewport="{ x: 0, y: 0, zoom: 1 }"
        :draggable="false"
        @init="onFlowInit"
        class="border-primary-lighter-stroke bg-primary-lighter p-lg rounded-xl border shadow-inner dark:border-slate-700 dark:bg-slate-800"
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

        <Panel
          v-if="selectedNode"
          position="top-left"
          class="bg-absolute-pure-white ring-offset-primary-lighter p-2xs max-w-[300px] rounded-lg ring-4 ring-offset-2"
          :class="{
            'ring-indigo-600 dark:ring-indigo-500/20': selectedNode.level > 1,
            'ring-focus-stroke dark:ring-purple-500/20': selectedNode.level <= 1,
          }"
        >
          <div class="space-y-1.5">
            <div class="flex items-start gap-2.5">
              <Avatar
                variant="secondary"
                :label="getInitials(selectedNode.firstName, selectedNode.lastName)"
                class="shrink-0"
              />
              <div>
                <h3 class="font-semibold">
                  {{ selectedNode.firstName }} {{ selectedNode.lastName }}
                </h3>
                <p class="text-sm">{{ selectedNode.position }}</p>
              </div>
            </div>
          </div>
        </Panel>
      </VueFlow>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useInitials } from '@/composables/useInitials'
import type { TeamMember } from '@/types/company'
import { Avatar, Button, Tag } from '@owlint/feathers-vue'
import { Panel, VueFlow, useVueFlow } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import { computed, nextTick, ref, watch } from 'vue'
import TeamMemberNode from './TeamMemberNode.vue'
import { useScreenshot } from '@/composables/useScreenshot'

interface Props {
  team: TeamMember[]
}

const { team } = defineProps<Props>()

interface TeamNodeData {
  position: string
  firstName: string
  lastName: string
  level: number
  selected?: boolean
}

interface TeamNode {
  id: string
  type: string
  position: { x: number; y: number }
  data: TeamNodeData
}

interface TeamEdge {
  id: string
  source: string
  target: string
  data: { level: number }
  style: Record<string, string | number>
}

interface LayoutNode extends TeamNode {
  subordinateIds: string[]
}

const { fitView, vueFlowRef } = useVueFlow()
const { getInitials } = useInitials()

// Generate nodes from team hierarchy
const nodes = computed(() => {
  if (!team) return []

  const generateNodes = (members: TeamMember[], level = 0): TeamNode[] => {
    let result: TeamNode[] = []

    for (const member of members) {
      const nodeId = `${member.position}-${member.firstName}-${member.lastName}`
      result.push({
        id: nodeId,
        type: 'team-member',
        position: { x: 0, y: 0 },
        data: {
          position: member.position,
          firstName: member.firstName,
          lastName: member.lastName,
          level,
        },
      })

      if (member.subordinates && member.subordinates.length > 0) {
        result = result.concat(generateNodes(member.subordinates, level + 1))
      }
    }

    return result
  }

  return generateNodes(team)
})

// Generate edges from team hierarchy
const edges = computed(() => {
  if (!team) return []

  const generateEdges = (members: TeamMember[], level = 0): TeamEdge[] => {
    let result: TeamEdge[] = []

    for (const member of members) {
      const sourceId = `${member.position}-${member.firstName}-${member.lastName}`

      if (member.subordinates && member.subordinates.length > 0) {
        for (const subordinate of member.subordinates) {
          const targetId = `${subordinate.position}-${subordinate.firstName}-${subordinate.lastName}`
          result.push({
            id: `${sourceId}-${targetId}`,
            source: sourceId,
            target: targetId,
            data: { level: level + 1 },
            style: {
              stroke: level === 0 ? 'var(--color-indigo-600)' : 'var(--color-almond-600)',
              strokeWidth: 4,
              strokeOpacity: 1,
            },
          })
        }

        result = result.concat(generateEdges(member.subordinates, level + 1))
      }
    }

    return result
  }

  return generateEdges(team)
})

// Layout algorithm
const applyLayout = () => {
  const NODE_WIDTH = 300
  const NODE_HEIGHT = 100
  const HORIZONTAL_PADDING = 30
  const VERTICAL_SPACING = 100

  const nodeMap = new Map<string, LayoutNode>()
  nodes.value.forEach((node) => {
    nodeMap.set(node.id, {
      ...node,
      subordinateIds: [],
      position: { x: 0, y: 0 },
    })
  })

  edges.value.forEach((edge) => {
    const parentNode = nodeMap.get(edge.source)
    if (parentNode) {
      parentNode.subordinateIds.push(edge.target)
    }
  })

  const leafNodes = Array.from(nodeMap.values()).filter((node) => node.subordinateIds.length === 0)

  let currentX = 0
  leafNodes.forEach((node) => {
    node.position = { x: currentX, y: 0 }
    currentX += NODE_WIDTH + HORIZONTAL_PADDING
  })

  const maxLevel = Math.max(...Array.from(nodeMap.values()).map((node) => node.data.level))
  for (let level = maxLevel - 1; level >= 0; level--) {
    const nodesAtLevel = Array.from(nodeMap.values()).filter((node) => node.data.level === level)

    nodesAtLevel.forEach((node) => {
      if (node.subordinateIds.length > 0) {
        const subordinatePositions = node.subordinateIds.map((id) => nodeMap.get(id)!.position)
        const minX = Math.min(...subordinatePositions.map((pos) => pos.x))
        const maxX = Math.max(...subordinatePositions.map((pos) => pos.x))
        const centerX = minX + (maxX - minX) / 2

        node.position = {
          x: centerX,
          y: -(maxLevel - level) * (NODE_HEIGHT + VERTICAL_SPACING),
        }
      }
    })
  }

  return nodes.value.map((node) => {
    const nodeWithPosition = nodeMap.get(node.id)!
    return {
      ...node,
      position: {
        x: nodeWithPosition.position.x - NODE_WIDTH / 2,
        y: nodeWithPosition.position.y,
      },
    }
  })
}

const layoutedNodes = ref<TeamNode[]>([])
const isInitialized = ref(false)

const onFlowInit = () => {
  isInitialized.value = true
  applyLayoutAndFitView()
}

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

const selectedNode = ref<TeamNodeData | null>(null)

const openTeamMemberCard = (data: TeamNodeData) => {
  selectedNode.value = data
}

const { capture } = useScreenshot()

const resetView = () => {
  selectedNode.value = null
  fitView({ duration: 500 })
}

const doScreenshot = () => {
  if (!vueFlowRef.value) {
    console.warn('VueFlow element not found')
    return
  }
  capture(vueFlowRef.value, { shouldDownload: true })
}

const focusMember = (member: TeamMember) => {
  const nodeId = `${member.position}-${member.firstName}-${member.lastName}`
  const node = nodes.value.find((n) => n.id === nodeId)

  if (node) {
    selectedNode.value = node.data
    nextTick(() => {
      const hierarchySection = document.querySelector('.vue-flow')
      if (hierarchySection) {
        hierarchySection.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
      setTimeout(() => {
        fitView({ nodes: [nodeId], duration: 800, padding: 0.5 })
      }, 500)
    })
  }
}

defineExpose({ focusMember })
</script>
