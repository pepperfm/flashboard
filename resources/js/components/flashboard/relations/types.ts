export type RelationScalarValue = string | number | boolean

export type RelationActionShape = {
  color?: string | null
  icon?: string | null
  key: string
  label?: string
  method?: string
  requires_confirmation?: boolean
  url?: string | null
  visible?: boolean
}

export type RelationRecordShape = {
  actions?: RelationActionShape[]
  attributes?: Record<string, unknown>
  key: RelationScalarValue
  links?: {
    detail?: string | null
    edit?: string | null
  }
  title: string
}

export type RelationPaginationShape = {
  current_page?: number
  has_more?: boolean
  next_page?: number | null
  per_page?: number
}

export type RelationEmptyStateShape = {
  description?: string
  title?: string
}

export type RelationManagerPayload = {
  actions?: RelationActionShape[]
  empty_state?: RelationEmptyStateShape
  key?: string
  label?: string
  options_url?: string | null
  pagination?: RelationPaginationShape | null
  per_page?: number
  read_only?: boolean
  records?: RelationRecordShape[]
  records_url?: string | null
  selected_record?: RelationRecordShape | null
  selected_records?: RelationRecordShape[]
  type?: 'has_one' | 'has_many' | string
}

export type LegacyRelationPayload = {
  key?: string
  label?: string
  records?: Array<{
    key: RelationScalarValue
    title: string
  }>
  type?: string
}

export type RelationOptionShape = {
  label?: string
  url?: string
  value?: RelationScalarValue
}

export type RelationOptionsResponse = {
  items?: RelationOptionShape[]
  meta?: {
    has_more?: boolean
    next_page?: number | null
  }
}

export type RelationPayloadShape = RelationManagerPayload | LegacyRelationPayload

export function isRelationManagerPayload(relation: RelationPayloadShape): relation is RelationManagerPayload {
  return relation.type === 'has_one' || relation.type === 'has_many'
}

export function hasRelationValue(value: RelationScalarValue | null | undefined): value is RelationScalarValue {
  return value !== null && value !== undefined && value !== ''
}
