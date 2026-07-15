const MODULE = 'bookings/home-bookings'

export const SERVICE_GROUPS_URL = () => {
  return { path: `${MODULE}/service-groups`, method: 'GET' }
}

export const SERVICES_URL = ({ service_group_home_id }) => {
  return { path: `${MODULE}/services?service_group_home_id=${service_group_home_id}`, method: 'GET' }
}

export const STAFF_URL = ({ service_home_id }) => {
  return { path: `${MODULE}/staff?service_home_id=${service_home_id}`, method: 'GET' }
}

export const STORE_URL = () => {
  return { path: `${MODULE}`, method: 'POST' }
}
