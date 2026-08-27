<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'

/*
 * A personal to-do list. It is deliberately client-only — kept in the browser
 * under a per-user key — so it needs no backend and stays private to whoever is
 * signed in on this device. If it ever needs to sync across devices, this is the
 * component to swap onto an API; nothing else depends on where it stores.
 */
const auth = useAuthStore()
const storageKey = computed(() => `todos:${auth.user?.id ?? 'anon'}`)

const items = ref([])
const draft = ref('')

onMounted(load)
watch(storageKey, load)

function load() {
  try {
    items.value = JSON.parse(localStorage.getItem(storageKey.value) || '[]')
  } catch {
    items.value = []
  }
}

function persist() {
  try {
    localStorage.setItem(storageKey.value, JSON.stringify(items.value))
  } catch {
    // Storage full or blocked — the list still works for this session.
  }
}

function add() {
  const text = draft.value.trim()
  if (!text) return
  items.value.unshift({ id: Date.now(), text, done: false })
  draft.value = ''
  persist()
}

function toggle(item) {
  item.done = !item.done
  persist()
}

function remove(id) {
  items.value = items.value.filter((i) => i.id !== id)
  persist()
}

const remaining = computed(() => items.value.filter((i) => !i.done).length)
</script>

<template>
  <section class="panel">
    <div class="panel-head">
      <h2>To-do</h2>
      <span class="ref">{{ remaining }} open</span>
    </div>
    <div class="panel-body">
      <div class="todo-add">
        <input
          v-model="draft"
          class="control"
          type="text"
          placeholder="Add a task and press Enter"
          maxlength="200"
          @keyup.enter="add"
        />
        <button class="btn btn-small" :disabled="!draft.trim()" @click="add">Add</button>
      </div>

      <ul v-if="items.length" class="todo-list">
        <li v-for="item in items" :key="item.id" class="todo" :class="{ done: item.done }">
          <button class="check" :aria-pressed="item.done" @click="toggle(item)">
            <AppIcon v-if="item.done" name="approvals" :size="14" />
          </button>
          <span class="todo-text">{{ item.text }}</span>
          <button class="todo-del" aria-label="Remove" @click="remove(item.id)">
            <AppIcon name="close" :size="14" />
          </button>
        </li>
      </ul>

      <p v-else class="ref todo-empty">Nothing on the list. Add a task above.</p>
    </div>
  </section>
</template>

<style scoped>
.todo-add {
  display: flex;
  gap: var(--s2);
  margin-bottom: var(--s4);
}

.todo-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.todo {
  display: flex;
  align-items: center;
  gap: var(--s3);
  padding: var(--s3) 0;
  border-bottom: 1px solid var(--rule);
}

.todo:last-child {
  border-bottom: 0;
}

.check {
  flex: 0 0 20px;
  width: 20px;
  height: 20px;
  border: 1.5px solid var(--rule-strong);
  border-radius: 5px;
  background: var(--surface);
  cursor: pointer;
  display: grid;
  place-items: center;
  color: #fff;
  transition: background 120ms ease, border-color 120ms ease;
}

.todo.done .check {
  background: var(--teal);
  border-color: var(--teal);
}

.todo-text {
  flex: 1;
  font-size: var(--step-0);
}

.todo.done .todo-text {
  color: var(--faint);
  text-decoration: line-through;
}

.todo-del {
  background: none;
  border: none;
  color: var(--faint);
  cursor: pointer;
  padding: 0.2rem;
  display: inline-flex;
  opacity: 0;
  transition: opacity 120ms ease, color 120ms ease;
}

.todo:hover .todo-del {
  opacity: 1;
}

.todo-del:hover {
  color: var(--red);
}

.todo-empty {
  padding: var(--s3) 0;
}
</style>
