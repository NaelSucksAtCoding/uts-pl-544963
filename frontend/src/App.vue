<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import api from './api'
import { auth } from './auth'
import AppIcon from './components/AppIcon.vue'

const router = useRouter()

const inisial = computed(() =>
  (auth.user?.name ?? '')
    .split(' ')
    .map((kata) => kata[0])
    .slice(0, 2)
    .join('')
    .toUpperCase(),
)

async function logout() {
  try {
    await api.post('/logout')
  } finally {
    auth.hapus()
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <header v-if="auth.isLoggedIn" class="topbar">
    <div class="topbar-inner">
      <RouterLink to="/bookings" class="brand">
        <span class="brand-mark">BF</span>
        <span class="brand-name">Booking Futsal</span>
      </RouterLink>

      <div class="topbar-right">
        <div class="user-chip">
          <span class="avatar">{{ inisial }}</span>
          <span class="user-name">{{ auth.user?.name }}</span>
        </div>
        <button class="btn btn-outline logout" @click="logout">
          <AppIcon name="log-out" />
          <span>Keluar</span>
        </button>
      </div>
    </div>
  </header>

  <main>
    <RouterView />
  </main>
</template>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgb(222 219 251 / 0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--line);
}

.topbar-inner {
  max-width: 1240px;
  margin: 0 auto;
  padding: 0.9rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.brand {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  color: var(--ink);
  text-decoration: none;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 12px;
  background: var(--primary);
  color: var(--white);
  font-weight: 800;
  font-size: 0.9rem;
  letter-spacing: 0.02em;
}

.brand-name {
  font-weight: 800;
  font-size: 1.1rem;
  letter-spacing: -0.01em;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-chip {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.3rem 0.9rem 0.3rem 0.3rem;
  border-radius: 999px;
  background: var(--surface);
}

.avatar {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  background: var(--primary-soft);
  color: var(--primary-strong);
  font-weight: 800;
  font-size: 0.8rem;
}

.user-name {
  font-weight: 600;
  font-size: 0.9rem;
}

.logout {
  padding: 0.55rem 1rem;
  font-size: 0.9rem;
}

@media (max-width: 560px) {
  .user-name,
  .brand-name {
    display: none;
  }
}
</style>
