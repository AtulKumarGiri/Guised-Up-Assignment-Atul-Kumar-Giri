import axios from 'axios';

// IMPORTANT: replace with your PC's local network IP (from `ipconfig`), NOT localhost.
// Your phone and PC must be on the same Wi-Fi network.
const API_BASE_URL = 'http://192.168.1.108:8000/api';

const client = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
});

export function setAuthToken(token) {
  client.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

export async function login(email, password) {
  const response = await client.post('/login', { email, password });
  setAuthToken(response.data.token);
  return response.data;
}

export async function getFeed(page = 1) {
  const response = await client.get(`/feed`, { params: { page } });
  return response.data;
}

export async function searchPosts(query) {
  const response = await client.get(`/search`, { params: { q: query } });
  return response.data;
}

export async function logInteraction(postId, type) {
  return client.post('/interactions', { post_id: postId, type });
}

export default client;
