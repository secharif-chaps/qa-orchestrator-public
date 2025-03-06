<template>
  <div class="bg-white p-4">
    <div class="h-96">
      <VueFlow
        class="h-full"
        :nodes="nodes"
        :edges="edges"
        @init="fitView()"
      >
        <template #node-company="props">
          <NodesCompanyNode v-bind="props" />
        </template>
        <template #node-partner="props">
          <NodesCompanyNode v-bind="props" />
        </template>
        <template #node-competitor="props">
          <NodesCompanyNode v-bind="props" />
        </template>
      </VueFlow>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { NodesCompanyNode } from '#components'
import { useVueFlow, VueFlow, type Edge, type Node } from '@vue-flow/core'
import * as d3 from 'd3'

const { fitView, findNode } = useVueFlow()

const { company } = useCompanyData()

const width = 928
const height = 384

function updateNodePositions(nodes: any[]) {
  for (const node of nodes) {
    const graphNode = findNode(node.id)
    if (!graphNode) continue
    graphNode.position.x = node.x
    graphNode.position.y = node.y
  }
}

function forceLayout(
  { nodes, edges }: { nodes: any[]; edges: any[] },
  callback: Function
) {
  const flowEdges = JSON.parse(JSON.stringify(edges))

  // Set node dimensions
  for (const node of nodes) {
    const graphNode = findNode(node.id)
    if (!graphNode) continue
    node.width = graphNode.dimensions.width
    node.height = graphNode.dimensions.height
  }

  const simulation = d3
    .forceSimulation(nodes)
    .force(
      'link',
      d3
        .forceLink(flowEdges)
        .id((d: any) => d.id)
        .distance(150)
    ) // Adjust distance as needed
    .force('charge', d3.forceManyBody().strength(-5000))
    .force('center', d3.forceCenter(width / 2, height / 2))
    .force(
      'x',
      d3
        .forceX((d: any) => {
          if (d.type === 'company') return width / 2 // Position company node not in the middle
          if (d.type === 'competitor') return width / 4 // Position competitors on the left
          if (d.type === 'partner') return (3 * width) / 4 // Position partners on the right
          return width / 2 // Default position
        })
        .strength(0.1)
    ) // Adjust strength as needed
    .force('y', d3.forceY(height / 2).strength(0.1)) // Center vertically

  simulation.on('tick', () => {
    updateNodePositions(nodes)
  })

  simulation.on('end', () => {
    callback()
  })
}

const nodes = computed(() => {
  const nodes: Node[] = []
  if (company.value.partners_and_competitors_graph) {
    company.value.partners_and_competitors_graph.nodes.forEach((node: any) => {
      nodes.push({
        id: node.id,
        type: node.type,
        data: {
          company: node.name,
        },
        position: { x: 0, y: 0 },
      })
    })
  }

  return nodes
})

const edges = computed(() => {
  let edges: Edge[] = []
  if (company.value.partners_and_competitors_graph) {
    edges = company.value.partners_and_competitors_graph.edges.map(
      (edge: any) => {
        return {
          id: `${edge.source}-${edge.target}`,
          source: edge.source,
          target: edge.target,
          type: 'straight',
        }
      }
    )
  }

  return edges
})

watch(
  () => nodes,
  () => {
    forceLayout({ nodes: nodes.value, edges: [...edges.value] }, () => {})
  },
  { immediate: true }
)
</script>
