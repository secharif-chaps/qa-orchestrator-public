<template>
  <div>
    <RouterView />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.read
</route>
