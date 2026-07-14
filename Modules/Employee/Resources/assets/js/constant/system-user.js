export const MODULE = '/app/employees/system-users'

export const EDIT_URL = (id) => {
  return { path: `${MODULE}/${id}/edit`, method: 'GET' }
}

export const STORE_URL = () => {
  return { path: `${MODULE}`, method: 'POST' }
}

export const UPDATE_URL = (id) => {
  return { path: `${MODULE}/${id}`, method: 'PUT' }
}
