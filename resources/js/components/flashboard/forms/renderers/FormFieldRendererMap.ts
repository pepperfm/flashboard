import type { Component } from 'vue'
import FBCheckbox from '../fields/FBCheckbox.vue'
import FBInput from '../fields/FBInput.vue'
import FBSelect from '../fields/FBSelect.vue'
import FBSwitch from '../fields/FBSwitch.vue'
import FBTextarea from '../fields/FBTextarea.vue'

export const formFieldRendererMap = {
  checkbox: FBCheckbox,
  input: FBInput,
  select: FBSelect,
  switch: FBSwitch,
  textarea: FBTextarea,
} satisfies Record<string, Component>

export type FormFieldRendererKey = keyof typeof formFieldRendererMap
