import { ref, shallowRef } from 'vue'
import { readError } from '@/api/client'

/**
 * The same three states appear on every screen that loads something: busy,
 * failed with a readable reason, or done. This keeps that logic in one place so
 * views stay about their subject.
 */
export function useAsync(loader, initial = null) {
  const data = shallowRef(initial)
  const error = ref(null)
  const loading = ref(false)
  const loaded = ref(false)

  async function run(...args) {
    loading.value = true
    error.value = null
    try {
      data.value = await loader(...args)
      loaded.value = true
      return data.value
    } catch (caught) {
      error.value = readError(caught)
      throw caught
    } finally {
      loading.value = false
    }
  }

  return { data, error, loading, loaded, run }
}
