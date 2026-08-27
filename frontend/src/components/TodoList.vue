<script setup>
import { ref } from 'vue'
import { useTodos } from '@/composables/useTodos'
import AppIcon from '@/components/AppIcon.vue'

/*
 * Personal to-do list. Tasks can carry a due date and time; those show on the
 * dashboard calendar and drive the "due soon" reminder. Storage and due-date
 * logic live in the useTodos composable so the calendar reads the same list.
 */
const { items, add, toggle, remove, remaining, dueNow } = useTodos()

const draft = ref('')
const draftDue = ref('')
const showDue = ref(false)

function submit() {
  add({ text: draft.value, due: draftDue.value || null })
  draft.value = ''
  draftDue.value = ''
  showDue.value = false
}

function formatDue(due) {
  if (!due) return ''
  const d = new Date(due)
  const hasTime = due.includes('T') && !due.endsWith('T00:00')
  return d.toLocaleDateString('en-MY', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    ...(hasTime ? { hour: 'numeric', minute: '2-digit' } : {}),
  })
}

function isOverdue(item) {
  return item.due && !item.done && new Date(item.due).getTime() <= Date.now()
}
</script>

<template>
  <section class="panel">
    <div class="panel-head">
      <h2>To-do</h2>
      <span class="ref">{{ remaining }} open</span>
    </div>
    <div class="panel-body">
      <div v-if="dueNow.length" class="due-banner">
        <AppIcon name="clock" :size="15" />
        <span>{{ dueNow.length }} task{{ dueNow.length > 1 ? 's' : '' }} due</span>
      </div>

      <div class="todo-add">
        <input
          v-model="draft"
          class="control"
          type="text"
          placeholder="Add a task and press Enter"
          maxlength="200"
          @keyup.enter="submit"
        />
        <button
          class="due-toggle"
          :class="{ on: showDue || draftDue }"
          title="Set a due date"
          @click="showDue = !showDue"
        >
          <AppIcon name="calendarCheck" :size="16" />
        </button>
        <button class="btn btn-small" :disabled="!draft.trim()" @click="submit">Add</button>
      </div>
      <div v-if="showDue" class="due-row">
        <label class="field-label" for="due">Due</label>
        <input id="due" v-model="draftDue" type="datetime-local" class="control control-mono" />
      </div>

      <ul v-if="items.length" class="todo-list">
        <li v-for="item in items" :key="item.id" class="todo" :class="{ done: item.done }">
          <button class="check" :aria-pressed="item.done" @click="toggle(item)">
            <AppIcon v-if="item.done" name="approvals" :size="14" />
          </button>
          <div class="todo-main">
            <span class="todo-text">{{ item.text }}</span>
            <span v-if="item.due" class="todo-due" :class="{ overdue: isOverdue(item) }">
              <AppIcon name="clock" :size="12" /> {{ formatDue(item.due) }}
            </span>
          </div>
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
.due-banner {
  display: flex;
  align-items: center;
  gap: var(--s2);
  padding: var(--s2) var(--s3);
  margin-bottom: var(--s3);
  background: var(--amber-wash);
  color: var(--amber);
  border-radius: var(--radius-sm);
  font-size: var(--step--1);
  font-weight: 600;
}

.todo-add {
  display: flex;
  gap: var(--s2);
  margin-bottom: var(--s3);
}

.due-toggle {
  flex: 0 0 auto;
  border: 1px solid var(--rule-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  padding: 0 var(--s2);
  display: inline-flex;
  align-items: center;
}

.due-toggle.on {
  background: var(--teal-wash);
  border-color: var(--teal-line);
  color: var(--teal);
}

.due-row {
  display: flex;
  align-items: center;
  gap: var(--s3);
  margin-bottom: var(--s4);
}

.due-row .control {
  flex: 1;
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

.todo-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.todo-text {
  font-size: var(--step-0);
}

.todo.done .todo-text {
  color: var(--faint);
  text-decoration: line-through;
}

.todo-due {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-family: var(--mono);
  font-size: 0.6875rem;
  color: var(--muted);
}

.todo-due.overdue {
  color: var(--red);
  font-weight: 600;
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
