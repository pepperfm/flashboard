import type { Component } from 'vue'
import type { FormContainerLayoutShape, FormFieldLayoutShape } from '../layout/resolveFormLayout'
import { formFieldRendererMap, type FormFieldRendererKey } from './FormFieldRendererMap'

type FormOptionValue = string | number | boolean

export type FormOptionShape = {
  label?: string
  value?: FormOptionValue
}

export type FormFieldShape = {
  kind?: 'field'
  help?: string
  hint?: string
  input_type?: string
  key: string
  label?: string
  layout?: FormFieldLayoutShape
  options?: FormOptionShape[] | Record<string, FormOptionValue>
  placeholder?: string
  renderer?: string
  required?: boolean
  type?: string
}

export type FormSectionShape = {
  kind: 'section'
  description?: string
  key: string
  label?: string
  layout?: FormContainerLayoutShape
  schema?: FormNodeShape[]
}

export type FormTabShape = {
  kind: 'tab'
  icon?: string
  key: string
  label?: string
  layout?: FormContainerLayoutShape
  schema?: FormNodeShape[]
}

export type FormTabsShape = {
  kind: 'tabs'
  key: string
  label?: string
  tabs?: FormTabShape[]
}

export type FormNodeShape = FormFieldShape | FormSectionShape | FormTabShape | FormTabsShape

const DEFAULT_FORM_FIELD_RENDERER: FormFieldRendererKey = 'input'

function isSystemIdField(field: FormFieldShape): boolean {
  return field.key === 'id'
}

function isFormFieldRendererKey(value: string): value is FormFieldRendererKey {
  return value in formFieldRendererMap
}

function fallbackRendererForType(type?: string): FormFieldRendererKey {
  if (type === 'select') {
    return 'select'
  }

  if (type === 'textarea') {
    return 'textarea'
  }

  if (type === 'toggle') {
    return 'switch'
  }

  return DEFAULT_FORM_FIELD_RENDERER
}

export function resolveFormFieldRendererKey(field: FormFieldShape): FormFieldRendererKey {
  if (isSystemIdField(field)) {
    return DEFAULT_FORM_FIELD_RENDERER
  }

  if (typeof field.renderer === 'string' && isFormFieldRendererKey(field.renderer)) {
    return field.renderer
  }

  if (field.renderer && import.meta.env.DEV) {
    throw new Error(`Unknown Flashboard form field renderer [${field.renderer}] for field [${field.key}].`)
  }

  return fallbackRendererForType(field.type)
}

export function resolveFormFieldRenderer(field: FormFieldShape): Component {
  return formFieldRendererMap[resolveFormFieldRendererKey(field)]
}

export function isToggleFieldRenderer(field: FormFieldShape): boolean {
  return resolveFormFieldRendererKey(field) === 'switch'
}

export function normalizeSelectItems(field: FormFieldShape): FormOptionShape[] {
  const options = field.options ?? []

  if (Array.isArray(options)) {
    return options
  }

  return Object.entries(options).map(([value, label]) => ({
    label: String(label),
    value,
  }))
}

export function resolveFormFieldRendererProps(field: FormFieldShape): Record<string, unknown> {
  const placeholder = field.placeholder ?? field.label ?? field.key
  const renderer = resolveFormFieldRendererKey(field)

  if (renderer === 'select') {
    return {
      items: normalizeSelectItems(field),
      name: field.key,
      placeholder,
      required: field.required,
    }
  }

  if (renderer === 'switch') {
    return {
      description: field.help ?? field.hint,
      label: field.label ?? field.key,
      name: field.key,
      required: field.required,
    }
  }

  if (renderer === 'textarea') {
    return {
      autoresize: true,
      name: field.key,
      placeholder,
      required: field.required,
      rows: 4,
    }
  }

  return {
    name: field.key,
    placeholder,
    readonly: isSystemIdField(field),
    required: field.required,
    type: field.input_type ?? 'text',
  }
}
