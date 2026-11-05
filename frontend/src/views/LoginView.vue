<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { auth } from '../auth'
import AppIcon from '../components/AppIcon.vue'
import SpecularButton from '../components/SpecularButton.vue'

const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})
const errors = ref({})
const pesan = ref('')
const loading = ref(false)

const fitur = [
  { icon: 'calendar-check', judul: 'Jadwal per lapangan', isi: 'Lihat semua booking yang dikelompokkan per tanggal.' },
  { icon: 'shield', judul: 'Bebas jadwal bentrok', isi: 'Booking di lapangan dan jam yang sama otomatis ditolak.' },
  { icon: 'wallet', judul: 'Status pembayaran', isi: 'Pantau penyewa yang belum bayar, DP, atau sudah lunas.' },
]

async function login() {
  loading.value = true
  errors.value = {}
  pesan.value = ''

  try {
    const { data } = await api.post('/login', form)
    auth.simpan(data.token, data.user)
    router.push({ name: 'bookings' })
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors ?? {}
    } else {
      pesan.value = 'Tidak dapat terhubung ke server API.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <section class="intro">
      <div class="brand">
        <span class="brand-mark">BF</span>
        <span class="brand-name">Booking Futsal</span>
      </div>

      <div class="intro-copy">
        <p class="eyebrow">Panel admin lapangan</p>
        <h1>Atur jadwal lapangan tanpa takut bentrok.</h1>
        <p class="lead">Catat penyewa, jam main, dan pembayaran dalam satu tempat.</p>
      </div>

      <ul class="fitur">
        <li v-for="item in fitur" :key="item.judul">
          <span class="fitur-icon"><AppIcon :name="item.icon" /></span>
          <div>
            <strong>{{ item.judul }}</strong>
            <p>{{ item.isi }}</p>
          </div>
        </li>
      </ul>
    </section>

    <section class="form-side">
      <form class="login-card" @submit.prevent="login">
        <h2>Masuk</h2>
        <p class="sub">Gunakan akun admin untuk mengelola booking.</p>

        <p v-if="pesan" class="alert">{{ pesan }}</p>

        <label class="field">
          <span class="field-label">Email</span>
          <span class="input" :class="{ invalid: errors.email }">
            <AppIcon name="mail" />
            <input v-model="form.email" type="email" autocomplete="username" placeholder="admin@futsal.test" />
          </span>
          <small v-if="errors.email" class="error">{{ errors.email[0] }}</small>
        </label>

        <label class="field">
          <span class="field-label">Password</span>
          <span class="input" :class="{ invalid: errors.password }">
            <AppIcon name="lock" />
            <input v-model="form.password" type="password" autocomplete="current-password" placeholder="Masukkan password" />
          </span>
          <small v-if="errors.password" class="error">{{ errors.password[0] }}</small>
        </label>

        <SpecularButton class="btn btn-primary btn-block" type="submit" :disabled="loading">
          <span>{{ loading ? 'Memproses...' : 'Masuk' }}</span>
          <AppIcon v-if="!loading" name="arrow-right" />
        </SpecularButton>
      </form>
    </section>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1.1fr 1fr;
}

.intro {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 3rem;
  padding: 3rem clamp(1.5rem, 5vw, 5rem);
  background: var(--primary-strong);
  color: var(--white);
}

.brand {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 12px;
  background: var(--bg);
  color: var(--primary-strong);
  font-weight: 800;
  font-size: 0.9rem;
}

.brand-name {
  font-weight: 800;
  font-size: 1.1rem;
}

.eyebrow {
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #a9dccf;
  margin-bottom: 1rem;
}

.intro h1 {
  font-size: clamp(2rem, 3.6vw, 3.1rem);
  line-height: 1.12;
  letter-spacing: -0.03em;
  max-width: 14ch;
}

.lead {
  margin-top: 1.25rem;
  font-size: 1.05rem;
  color: #d3ece6;
  max-width: 38ch;
  line-height: 1.6;
}

.fitur {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 1.25rem;
}

.fitur li {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.fitur-icon {
  display: grid;
  place-items: center;
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 14px;
  background: rgb(255 255 255 / 0.1);
  color: #a9dccf;
  font-size: 1.1rem;
}

.fitur strong {
  display: block;
  font-size: 0.98rem;
}

.fitur p {
  margin-top: 0.2rem;
  color: #c3e3dc;
  font-size: 0.9rem;
  line-height: 1.5;
}

.form-side {
  display: grid;
  place-items: center;
  padding: 3rem 1.5rem;
}

.login-card {
  width: 100%;
  max-width: 400px;
  display: grid;
  gap: 1.25rem;
}

.login-card h2 {
  font-size: 2rem;
  letter-spacing: -0.02em;
}

.sub {
  color: var(--ink-2);
  margin-top: -0.75rem;
  margin-bottom: 0.5rem;
}

.alert {
  padding: 0.8rem 1.1rem;
  border-radius: var(--radius-md);
  background: var(--rose-soft);
  color: var(--rose);
  font-weight: 600;
  font-size: 0.9rem;
}

.btn-block {
  margin-top: 0.5rem;
}

@media (max-width: 860px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .intro {
    gap: 2rem;
    padding: 2rem 1.5rem;
  }

  .fitur {
    display: none;
  }
}
</style>
