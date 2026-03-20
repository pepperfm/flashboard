<script setup lang="ts">
defineProps<{
  panel: {
    name: string
  }
  payload: {
    metadata: {
      screen_key: string
      screen_kind: string
    }
  }
  stateMessage: string
}>()
</script>

<template>
  <UCard variant="subtle">
    <template #header>
      <div class="card-header">
        <h2>{{ panel.name }}</h2>
        <UBadge color="neutral" variant="soft">
          {{ payload.metadata.screen_key }}
        </UBadge>
      </div>
    </template>

    <div class="state" v-if="stateMessage">
      <UAlert :description="stateMessage" color="neutral" variant="subtle" />
    </div>
  </UCard>
</template>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

h2 {
  margin: 0;
  font-size: 1.2rem;
}

.state {
  margin-top: 0.5rem;
}
</style>
