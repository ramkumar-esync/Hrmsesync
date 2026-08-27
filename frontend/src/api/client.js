import axios from 'axios'

/**
 * One axios instance for the whole app.
 *
 * The token is attached here rather than in each caller, and a 401 is treated
 * as a single event — the session ended — so no view has to handle it.
 */
const client = axios.create({
  baseURL: `${import.meta.env.VITE_API_BASE_URL || ''}/api`,
  headers: { Accept: 'application/json' },
})

let onUnauthenticated = () => {}

export function setUnauthenticatedHandler(handler) {
  onUnauthenticated = handler
}

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('payroll_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) onUnauthenticated()
    return Promise.reject(error)
  },
)

/**
 * Turns any failure into one readable sentence.
 *
 * The API speaks in three registers — Laravel validation bags, domain rule
 * violations, and plain HTTP errors — and a person filling in a form should not
 * have to tell them apart.
 */
export function readError(error) {
  const response = error?.response

  if (!response) return 'Cannot reach the server. Check your connection and try again.'

  const { data, status } = response

  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) return first[0]
  }

  if (data?.message) return data.message

  if (status === 403) return 'You do not have access to this.'
  if (status === 404) return 'That record no longer exists.'
  if (status >= 500) return 'Something failed on the server. Try again shortly.'

  return 'That did not work. Try again.'
}

/** Field-level validation messages, for showing errors next to their input. */
export function readFieldErrors(error) {
  const errors = error?.response?.data?.errors
  if (!errors) return {}
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0]]),
  )
}

export default client
