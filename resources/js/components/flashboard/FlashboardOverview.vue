<script setup lang="ts">
defineProps<{
  panel: {
    name: string
    path: string
    route_name_prefix: string
  }
  payload: {
    metadata: {
      screen_key: string
      screen_kind: string
    }
    schema_version?: string
  }
  stateMessage: string
  themeName?: string
  version: string
}>()
</script>

<template>
  <UCard variant="soft">
    <template #header>
      <div class="card-header">
        <div>
          <p class="eyebrow">Flashboard Inertia + Nuxt UI</p>
          <h2>{{ panel.name }}</h2>
        </div>
      </div>
    </template>

    <div class="summary-grid">
      <div>
        <strong>Route</strong>
        <span>/{{ panel.path }}</span>
      </div>
      <div>
        <strong>Route Prefix</strong>
        <span>{{ panel.route_name_prefix }}</span>
      </div>
      <div>
        <strong>Version</strong>
        <span>{{ version }}</span>
      </div>
      <div>
        <strong>Screen</strong>
        <UBadge color="neutral" variant="soft">
          {{ payload.metadata.screen_key }}
        </UBadge>
      </div>
      <div>
        <strong>Kind</strong>
        <UBadge color="primary" variant="subtle">
          {{ payload.metadata.screen_kind }}
        </UBadge>
      </div>
      <div>
        <strong>Schema</strong>
        <UBadge color="secondary" variant="outline">
          {{ payload.schema_version ?? 'unknown' }}
        </UBadge>
      </div>
      <div>
        <strong>Theme</strong>
        <UBadge color="neutral" variant="outline">
          {{ themeName ?? 'default' }}
        </UBadge>
      </div>
    </div>

    <div class="state">
      <UAlert :description="stateMessage" color="neutral" variant="subtle" />
    </div>
  </UCard>
</template>

<style scoped>
.card-header {
  display: grid;
  gap: 1rem;
}

.eyebrow {
  margin: 0 0 0.5rem;
  font-size: 0.75rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #75624d;
}

h2 {
  margin: 0;
  font-size: 1.6rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
}

.summary-grid strong {
  display: block;
  margin-bottom: 0.25rem;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #866b52;
}

.state {
  margin-top: 1rem;
}

@media (max-width: 900px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
