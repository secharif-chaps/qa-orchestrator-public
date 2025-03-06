<template>
  <ClientOnly>
    <LMap
      style="height: 650px; z-index: 0"
      ref="map"
      :zoom="zoom"
      :center="[47.21322, -1.559482]"
      :use-global-leaflet="false"
    >
      <LTileLayer
        :url="url"
        attribution="ChapsMap"
        layer-type="base"
        name="ChapsMap"
      />
      <LGeoJson
        v-if="data"
        :geojson="data"
        :options-style="options"
      />
    </LMap>
  </ClientOnly>
</template>

<script lang="ts" setup>
const props = defineProps<{
  data: any
  options: any
}>()

const zoom = ref(2)

const config = useRuntimeConfig()

const apiUrl = config.public.tilesApiUrl
const apiKey = config.public.tilesApiKey
const style = `klokantech-basic/256`

const url = ref(`${apiUrl}styles/${style}/{z}/{x}/{y}.png?apikey=${apiKey}`)

const map = useTemplateRef<any>('map')
</script>
