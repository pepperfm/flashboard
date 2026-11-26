import { describe, expect, test } from 'bun:test'
import { normalizeNumberInputValue } from '../../resources/js/components/flashboard/forms/fields/numberInputValue'

describe('number input values', () => {
  test('preserves zero, integer and decimal values from model state', () => {
    expect(normalizeNumberInputValue(0)).toBe(0)
    expect(normalizeNumberInputValue('0')).toBe(0)
    expect(normalizeNumberInputValue('125.50')).toBe(125.5)
    expect(normalizeNumberInputValue(2)).toBe(2)
    expect(normalizeNumberInputValue('-0.125')).toBe(-0.125)
  })

  test('does not turn blank or invalid values into zero', () => {
    for (const value of [null, undefined, '', '  ', 'invalid', NaN, Infinity, false, []]) {
      expect(normalizeNumberInputValue(value)).toBeUndefined()
    }
  })
})
