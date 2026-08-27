/*
 * Presentation helpers. Amounts arrive from the API as an object with a decimal
 * string and a currency, never as a float, and they are formatted here rather
 * than being re-computed anywhere in the client.
 */

export function money(amount, { showCurrency = true } = {}) {
  if (amount === null || amount === undefined) return '—'

  const value = typeof amount === 'object' ? amount.amount : amount
  const currency = typeof amount === 'object' ? amount.currency : null

  const formatted = Number(value).toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

  return showCurrency && currency ? `${currency} ${formatted}` : formatted
}

export function days(value) {
  if (value === null || value === undefined) return '—'
  const number = Number(value)
  const text = Number.isInteger(number) ? String(number) : number.toFixed(1)
  return `${text} ${number === 1 ? 'day' : 'days'}`
}

export function date(value, { withYear = true } = {}) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-MY', {
    day: 'numeric',
    month: 'short',
    ...(withYear ? { year: 'numeric' } : {}),
  })
}

export function dateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('en-MY', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function dateRange(from, to) {
  if (!from) return '—'
  if (!to || from === to) return date(from)

  const sameYear = new Date(from).getFullYear() === new Date(to).getFullYear()
  return `${date(from, { withYear: !sameYear })} – ${date(to)}`
}

export function period(value) {
  if (!value) return '—'
  const [year, month] = value.split('-')
  return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString('en-MY', {
    month: 'long',
    year: 'numeric',
  })
}

export function shortRef(id) {
  return id ? id.slice(0, 8).toUpperCase() : '—'
}

/** Maps a domain status onto one of the tag styles. */
export function statusTone(status) {
  return (
    {
      approved: 'positive',
      issued: 'positive',
      paid: 'positive',
      finalised: 'brand',
      pending: 'pending',
      draft: 'neutral',
      rejected: 'negative',
      cancelled: 'negative',
      superseded: 'neutral',
    }[status] ?? 'neutral'
  )
}

export function statusLabel(status) {
  return (
    {
      pending: 'Awaiting approval',
      approved: 'Approved',
      rejected: 'Not approved',
      cancelled: 'Cancelled',
      draft: 'Draft',
      issued: 'Issued',
      superseded: 'Superseded',
      finalised: 'Finalised',
      paid: 'Paid',
    }[status] ?? status
  )
}
