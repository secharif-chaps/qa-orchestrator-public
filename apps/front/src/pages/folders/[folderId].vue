<template>
  <div>
    <RouterView />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>
