import axios from 'axios'

const readMetaCsrfToken = () =>
  document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const applyCsrfDefaults = () => {
  const token = readMetaCsrfToken()
  if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token
  }
}

axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true
axios.defaults.xsrfCookieName = 'XSRF-TOKEN'
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
applyCsrfDefaults()

axios.interceptors.request.use((config) => {
  const token = readMetaCsrfToken()
  if (!token) {
    return config
  }

  if (config.headers && typeof config.headers.set === 'function') {
    config.headers.set('X-CSRF-TOKEN', token)
  } else {
    config.headers = {
      ...(config.headers || {}),
      'X-CSRF-TOKEN': token,
    }
  }

  return config
})

if (typeof document !== 'undefined') {
  document.addEventListener('inertia:success', applyCsrfDefaults)
}

export default axios
