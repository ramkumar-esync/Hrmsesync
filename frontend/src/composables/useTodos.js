import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

/*
 * The to-do list, owned in one place so both the TodoList widget and the
 * dashboard calendar read the same tasks. Tasks live in the browser under a
 * per-user key — private to whoever is signed in on this device, no backend.
 *
 * Each task may carry a due date and time; tasks with a due date show on the
 * calendar and drive the "due soon" reminder. The state is module-level so
 * every caller shares one reactive list rather than each keeping its own copy.
 */
const items = ref([])
let boundKey = null

function keyFor(userId) {
  return `todos:${userId ?? 'anon'}`
}

function load(key) {
  try {
    items.value = JSON.parse(localStorage.getItem(key) || '[]')
  } catch {
    items.value = []
  }
}

function persist(key) {
  try {
    localStorage.setItem(key, JSON.stringify(items.value))
  } catch {
    // Storage full or blocked — the list still works for this session.
  }
}

export function useTodos() {
  const auth = useAuthStore()
  const storageKey = computed(() => keyFor(auth.user?.id))

  // Load when first used or when the signed-in user changes.
  if (boundKey !== storageKey.value) {
    boundKey = storageKey.value
    load(boundKey)
  }
  watch(storageKey, (key) => {
    boundKey = key
    load(key)
  })

  function save() {
    persist(boundKey)
  }

  function add({ text, due = null }) {
    const clean = (text ?? '').trim()
    if (!clean) return
    items.value.unshift({ id: Date.now(), text: clean, due, done: false })
    save()
  }

  function toggle(item) {
    item.done = !item.done
    save()
  }

  function remove(id) {
    items.value = items.value.filter((i) => i.id !== id)
    save()
  }

  function setDue(item, due) {
    item.due = due || null
    save()
  }

  const remaining = computed(() => items.value.filter((i) => !i.done).length)

  // Tasks that are due now or overdue and not yet done — the reminder set.
  const dueNow = computed(() => {
    const now = Date.now()
    return items.value
      .filter((i) => !i.done && i.due && new Date(i.due).getTime() <= now)
      .sort((a, b) => new Date(a.due) - new Date(b.due))
  })

  // Upcoming (due in the future, not done), soonest first.
  const upcoming = computed(() => {
    const now = Date.now()
    return items.value
      .filter((i) => !i.done && i.due && new Date(i.due).getTime() > now)
      .sort((a, b) => new Date(a.due) - new Date(b.due))
  })

  return { items, add, toggle, remove, setDue, remaining, dueNow, upcoming }
}
