import axios from 'axios'

/**
 * One Axios instance, and one interceptor that turns any failure into a message
 * the app can show. No component handles HTTP errors itself.
 */
const client = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    const data = error.response?.data
    error.friendly =
      data?.message ??
      (error.code === 'ERR_NETWORK'
        ? 'Could not reach the server. Is it still running?'
        : 'Something went wrong. Please try again.')
    // 422 bodies carry per-field messages; the form reads these directly.
    error.fields = data?.errors ?? {}
    return Promise.reject(error)
  },
)

export default client
