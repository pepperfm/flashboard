import type { Component } from 'vue'
import FBCheckbox from '../fields/FBCheckbox.vue'
import FBDateInput from '../fields/FBDateInput.vue'
import FBFileUpload from '../fields/FBFileUpload.vue'
import FBInput from '../fields/FBInput.vue'
import FBRelationMultiSelect from '../fields/FBRelationMultiSelect.vue'
import FBRelationSelect from '../fields/FBRelationSelect.vue'
import FBRichText from '../fields/FBRichText.vue'
import FBSelect from '../fields/FBSelect.vue'
import FBSwitch from '../fields/FBSwitch.vue'
import FBTextarea from '../fields/FBTextarea.vue'

export const formFieldRendererMap = {
  checkbox: FBCheckbox,
  date: FBDateInput,
  file_upload: FBFileUpload,
  input: FBInput,
  relation_multi_select: FBRelationMultiSelect,
  relation_select: FBRelationSelect,
  rich_text: FBRichText,
  select: FBSelect,
  switch: FBSwitch,
  textarea: FBTextarea,
} satisfies Record<string, Component>

export type FormFieldRendererKey = keyof typeof formFieldRendererMap
