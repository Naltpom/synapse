/**
 * Client API minimal : cookies de session en same-origin (proxy Vite en dev,
 * reverse proxy nginx en Docker), redirection vers /login sur 401.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly details?: Record<string, string>,
  ) {
    super(message)
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(path, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...init.headers },
    ...init,
  })

  // Session expirée en cours d'usage → retour au login. Jamais pour la sonde /api/me
  // ni depuis la page de login elle-même (sinon boucle de rechargement).
  if (
    response.status === 401 &&
    !path.endsWith('/login') &&
    !path.endsWith('/me') &&
    window.location.pathname !== '/login'
  ) {
    window.location.href = '/login'
    throw new ApiError(401, 'Session expirée.')
  }

  const body = response.status === 204 ? null : await response.json()

  if (!response.ok) {
    throw new ApiError(response.status, body?.error ?? 'Erreur inattendue.', body?.errors)
  }

  return body as T
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, payload: unknown) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(payload) }),
  patch: <T>(path: string, payload: unknown) =>
    request<T>(path, { method: 'PATCH', body: JSON.stringify(payload) }),
  put: <T>(path: string, payload: unknown) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(payload) }),
}
