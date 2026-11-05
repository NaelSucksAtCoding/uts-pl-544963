<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import api from '../api'
import AppIcon from '../components/AppIcon.vue'
import SpecularButton from '../components/SpecularButton.vue'

const STATUS = {
  belum_bayar: 'Belum Bayar',
  dp: 'DP',
  lunas: 'Lunas',
}
const JUMLAH_LAPANGAN = 10

const bookings = ref([])
const loading = ref(true)
const pesan = ref(null)

const tab = ref('semua')
const cari = ref('')

const formTerbuka = ref(false)
const editId = ref(null)
const errors = ref({})
const menyimpan = ref(false)
const form = reactive(formKosong())
const inputNama = ref(null)
const sorotId = ref(null)

const hapusTarget = ref(null)

function formKosong() {
  return {
    nama_penyewa: '',
    nomor_lapangan: 1,
    jam_mulai: '',
    jam_selesai: '',
    total_harga: 0,
    status_pembayaran: 'belum_bayar',
  }
}

// API memakai format "Y-m-d H:i", sedangkan input datetime-local memakai "Y-m-dTH:i".
const keInput = (waktu) => waktu.replace(' ', 'T')
const keApi = (waktu) => waktu.replace('T', ' ')

const rupiah = (angka) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(angka || 0)

const jam = (waktu) => waktu.slice(11, 16)

function menitAntara(mulai, selesai) {
  if (!mulai || !selesai) return 0
  return (new Date(selesai.replace(' ', 'T')) - new Date(mulai.replace(' ', 'T'))) / 60000
}

function teksDurasi(menit) {
  if (menit <= 0) return '-'
  const j = Math.floor(menit / 60)
  const m = menit % 60
  return [j && `${j} jam`, m && `${m} mnt`].filter(Boolean).join(' ')
}

const labelTanggal = (tanggal) =>
  new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(
    new Date(`${tanggal}T00:00`),
  )

const jumlahPerStatus = computed(() => {
  const hasil = { semua: bookings.value.length, belum_bayar: 0, dp: 0, lunas: 0 }
  bookings.value.forEach((b) => hasil[b.status_pembayaran]++)
  return hasil
})

const kelompokTanggal = computed(() => {
  const kataKunci = cari.value.trim().toLowerCase()
  const kelompok = new Map()

  bookings.value
    .filter((b) => tab.value === 'semua' || b.status_pembayaran === tab.value)
    .filter((b) => b.nama_penyewa.toLowerCase().includes(kataKunci))
    .forEach((b) => {
      const tanggal = b.jam_mulai.slice(0, 10)
      if (!kelompok.has(tanggal)) kelompok.set(tanggal, [])
      kelompok.get(tanggal).push(b)
    })

  return [...kelompok].map(([tanggal, items]) => ({ tanggal, label: labelTanggal(tanggal), items }))
})

const durasiForm = computed(() => teksDurasi(menitAntara(form.jam_mulai, form.jam_selesai)))

// Posisi latar hijau yang bergeser di pilihan status pembayaran.
const indeksStatus = computed(() => Object.keys(STATUS).indexOf(form.status_pembayaran))

// Kartu booking yang baru disimpan diberi sorotan sesaat.
function sorot(id) {
  sorotId.value = id
  setTimeout(() => {
    if (sorotId.value === id) sorotId.value = null
  }, 1600)
}

function tampilkanPesan(teks, jenis = 'sukses') {
  pesan.value = { teks, jenis }
  setTimeout(() => (pesan.value = null), 3000)
}

async function muatBookings() {
  loading.value = true

  try {
    const { data } = await api.get('/bookings')
    bookings.value = data.data
  } catch {
    tampilkanPesan('Gagal memuat data booking.', 'gagal')
  } finally {
    loading.value = false
  }
}

async function bukaForm() {
  errors.value = {}
  formTerbuka.value = true
  await nextTick()
  inputNama.value?.focus()
}

function tutupForm() {
  formTerbuka.value = false
  editId.value = null
  errors.value = {}
}

function mulaiTambah() {
  Object.assign(form, formKosong())
  editId.value = null
  bukaForm()
}

function mulaiUbah(booking) {
  Object.assign(form, {
    nama_penyewa: booking.nama_penyewa,
    nomor_lapangan: booking.nomor_lapangan,
    jam_mulai: keInput(booking.jam_mulai),
    jam_selesai: keInput(booking.jam_selesai),
    total_harga: booking.total_harga,
    status_pembayaran: booking.status_pembayaran,
  })
  editId.value = booking.id
  bukaForm()
}

async function simpan() {
  menyimpan.value = true
  errors.value = {}

  const payload = {
    ...form,
    jam_mulai: keApi(form.jam_mulai),
    jam_selesai: keApi(form.jam_selesai),
  }

  try {
    let respons
    if (editId.value) {
      respons = await api.put(`/bookings/${editId.value}`, payload)
      tampilkanPesan('Booking berhasil diubah.')
    } else {
      respons = await api.post('/bookings', payload)
      tampilkanPesan('Booking berhasil dibuat.')
    }

    tutupForm()
    await muatBookings()
    sorot(respons.data.data.id)
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors ?? {}
    } else {
      tampilkanPesan('Gagal menyimpan booking.', 'gagal')
    }
  } finally {
    menyimpan.value = false
  }
}

async function hapus() {
  const target = hapusTarget.value

  try {
    await api.delete(`/bookings/${target.id}`)
    tampilkanPesan('Booking berhasil dihapus.')
    if (editId.value === target.id) tutupForm()
    await muatBookings()
  } catch {
    tampilkanPesan('Gagal menghapus booking.', 'gagal')
  } finally {
    hapusTarget.value = null
  }
}

// Tombol Esc menutup dialog yang sedang terbuka.
function tanganiEsc(event) {
  if (event.key !== 'Escape') return
  if (hapusTarget.value) hapusTarget.value = null
  else if (formTerbuka.value) tutupForm()
}

// Halaman di belakang pop up tidak ikut ter-scroll saat pop up terbuka.
watch([formTerbuka, hapusTarget], ([formBuka, hapusBuka]) => {
  document.body.style.overflow = formBuka || hapusBuka ? 'hidden' : ''
})

onMounted(() => {
  muatBookings()
  window.addEventListener('keydown', tanganiEsc)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', tanganiEsc)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="layout">
    <!-- Daftar booking -->
    <section class="schedule">
      <div class="page-head">
        <div>
          <h1>Jadwal Booking</h1>
          <p class="muted">{{ bookings.length }} booking terdaftar</p>
        </div>
        <SpecularButton class="btn btn-primary" @click="mulaiTambah">
          <AppIcon name="plus" />
          <span>Booking baru</span>
        </SpecularButton>
      </div>

      <label class="input search">
        <AppIcon name="search" />
        <input v-model="cari" type="search" placeholder="Cari nama penyewa" />
      </label>

      <nav class="tabs" aria-label="Filter status pembayaran">
        <button
          v-for="(label, value) in { semua: 'Semua', ...STATUS }"
          :key="value"
          class="tab"
          :class="{ active: tab === value }"
          @click="tab = value"
        >
          {{ label }}
          <span class="tab-count">{{ jumlahPerStatus[value] }}</span>
        </button>
      </nav>

      <div v-if="loading && bookings.length === 0" class="empty">Memuat data booking...</div>

      <div v-else-if="kelompokTanggal.length === 0" class="empty">
        <AppIcon name="inbox" class="empty-icon" />
        <p>{{ bookings.length === 0 ? 'Belum ada booking.' : 'Tidak ada booking yang cocok.' }}</p>
      </div>

      <div v-for="grup in kelompokTanggal" v-else :key="grup.tanggal" class="day-group">
        <h3 class="day-label">
          <AppIcon name="calendar" />
          {{ grup.label }}
        </h3>

        <article
          v-for="(booking, urutan) in grup.items"
          :key="booking.id"
          class="booking-card"
          :class="{ editing: editId === booking.id, sorot: sorotId === booking.id }"
          :style="{ '--urutan': urutan }"
        >
          <div class="time-tile">
            <strong>{{ jam(booking.jam_mulai) }}</strong>
            <span>{{ teksDurasi(menitAntara(booking.jam_mulai, booking.jam_selesai)) }}</span>
          </div>

          <div class="info">
            <h4>{{ booking.nama_penyewa }}</h4>
            <p class="meta">
              <span><AppIcon name="pin" /> Lapangan {{ booking.nomor_lapangan }}</span>
              <span><AppIcon name="clock" /> {{ jam(booking.jam_mulai) }} – {{ jam(booking.jam_selesai) }}</span>
            </p>
            <span class="status" :class="`status-${booking.status_pembayaran}`">
              {{ STATUS[booking.status_pembayaran] }}
            </span>
          </div>

          <div class="price">{{ rupiah(booking.total_harga) }}</div>

          <div class="actions">
            <button class="icon-btn" :aria-label="`Ubah booking ${booking.nama_penyewa}`" @click="mulaiUbah(booking)">
              <AppIcon name="pencil" />
            </button>
            <button
              class="icon-btn danger"
              :aria-label="`Hapus booking ${booking.nama_penyewa}`"
              @click="hapusTarget = booking"
            >
              <AppIcon name="trash" />
            </button>
          </div>
        </article>
      </div>
    </section>

    <!-- Form tambah / ubah: pop up di tengah layar -->
    <Transition name="pop" :duration="{ enter: 400, leave: 150 }">
      <div v-if="formTerbuka" class="modal-backdrop" @click.self="tutupForm">
        <form
          class="form-modal pop-card"
          role="dialog"
          aria-modal="true"
          aria-labelledby="judul-form"
          @submit.prevent="simpan"
        >
          <div class="form-head">
            <div>
              <h2 id="judul-form">{{ editId ? 'Ubah Booking' : 'Booking Baru' }}</h2>
              <p class="muted">{{ editId ? 'Perbarui data booking yang dipilih.' : 'Isi data penyewa dan jadwal main.' }}</p>
            </div>
            <button type="button" class="icon-btn" aria-label="Tutup form" @click="tutupForm">
              <AppIcon name="x" />
            </button>
          </div>

          <label class="field">
            <span class="field-label">Nama Penyewa</span>
            <span class="input" :class="{ invalid: errors.nama_penyewa }">
              <input ref="inputNama" v-model="form.nama_penyewa" type="text" placeholder="Contoh: Budi Santoso" />
            </span>
            <small v-if="errors.nama_penyewa" class="error">{{ errors.nama_penyewa[0] }}</small>
          </label>

          <div class="field">
            <span class="field-label">Lapangan</span>
            <div class="court-grid" role="group" aria-label="Pilih lapangan">
              <button
                v-for="nomor in JUMLAH_LAPANGAN"
                :key="nomor"
                type="button"
                class="court"
                :class="{ selected: form.nomor_lapangan === nomor }"
                :aria-pressed="form.nomor_lapangan === nomor"
                @click="form.nomor_lapangan = nomor"
              >
                {{ nomor }}
              </button>
            </div>
            <small v-if="errors.nomor_lapangan" class="error">{{ errors.nomor_lapangan[0] }}</small>
          </div>

          <div class="field">
            <div class="label-row">
              <span class="field-label">Jadwal Main</span>
              <span class="durasi">Durasi {{ durasiForm }}</span>
            </div>

            <label class="input time-input" :class="{ invalid: errors.jam_mulai }">
              <span class="prefix">Mulai</span>
              <input v-model="form.jam_mulai" type="datetime-local" aria-label="Jam mulai" />
            </label>
            <small v-if="errors.jam_mulai" class="error">{{ errors.jam_mulai[0] }}</small>

            <label class="input time-input" :class="{ invalid: errors.jam_selesai }">
              <span class="prefix">Selesai</span>
              <input v-model="form.jam_selesai" type="datetime-local" aria-label="Jam selesai" />
            </label>
            <small v-if="errors.jam_selesai" class="error">{{ errors.jam_selesai[0] }}</small>
          </div>

          <label class="field">
            <span class="field-label">Total Harga</span>
            <span class="input" :class="{ invalid: errors.total_harga }">
              <span class="prefix">Rp</span>
              <input v-model.number="form.total_harga" type="number" min="0" step="1000" aria-label="Total harga" />
            </span>
            <small v-if="errors.total_harga" class="error">{{ errors.total_harga[0] }}</small>
          </label>

          <div class="field">
            <span class="field-label">Status Pembayaran</span>
            <div class="segmented" role="group" aria-label="Status pembayaran" :style="{ '--indeks': indeksStatus }">
              <span class="segmented-indicator" aria-hidden="true" />
              <button
                v-for="(label, value) in STATUS"
                :key="value"
                type="button"
                :class="{ active: form.status_pembayaran === value }"
                :aria-pressed="form.status_pembayaran === value"
                @click="form.status_pembayaran = value"
              >
                {{ label }}
              </button>
            </div>
            <small v-if="errors.status_pembayaran" class="error">{{ errors.status_pembayaran[0] }}</small>
          </div>

          <div class="form-footer">
            <SpecularButton type="submit" class="btn btn-primary btn-block" :disabled="menyimpan">
              <template v-if="menyimpan">Menyimpan...</template>
              <template v-else>
                {{ editId ? 'Simpan Perubahan' : 'Simpan Booking' }} ({{ rupiah(form.total_harga) }})
              </template>
            </SpecularButton>
          </div>
        </form>
      </div>
    </Transition>

    <Transition name="toast">
      <div v-if="pesan" class="toast" :class="pesan.jenis" role="status">{{ pesan.teks }}</div>
    </Transition>

    <!-- Konfirmasi hapus -->
    <Transition name="pop" :duration="{ enter: 400, leave: 150 }">
      <div v-if="hapusTarget" class="modal-backdrop" @click.self="hapusTarget = null">
        <div class="modal pop-card" role="dialog" aria-modal="true" aria-labelledby="judul-hapus">
          <h2 id="judul-hapus">Hapus booking ini?</h2>
          <p>
            Booking atas nama <strong>{{ hapusTarget.nama_penyewa }}</strong> di Lapangan
            {{ hapusTarget.nomor_lapangan }}, {{ labelTanggal(hapusTarget.jam_mulai.slice(0, 10)) }} pukul
            {{ jam(hapusTarget.jam_mulai) }}, akan dihapus permanen.
          </p>
          <div class="modal-actions">
            <button class="btn btn-outline" @click="hapusTarget = null">Batal</button>
            <button class="btn btn-danger" @click="hapus">Hapus</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.layout {
  max-width: 1040px;
  margin: 0 auto;
  padding: 2rem 1.5rem 4rem;
}

.muted {
  color: var(--muted);
  margin-top: 0.3rem;
}

/* ---------- Daftar ---------- */
.page-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}

.page-head h1 {
  font-size: clamp(1.6rem, 2.5vw, 2.1rem);
  letter-spacing: -0.03em;
}

.search {
  max-width: 420px;
}

.tabs {
  display: flex;
  gap: 1.75rem;
  margin: 1.5rem 0 0.5rem;
  border-bottom: 1px solid var(--line);
  overflow-x: auto;
}

.tab {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.6rem 0 0.85rem;
  border: 0;
  background: none;
  color: var(--ink-2);
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.tab.active {
  color: var(--ink);
  font-weight: 800;
}

.tab.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 3px;
  border-radius: 3px;
  background: var(--primary);
}

.tab-count {
  min-width: 1.5rem;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink-2);
  font-size: 0.78rem;
  font-weight: 700;
  text-align: center;
}

.tab.active .tab-count {
  background: var(--primary);
  color: var(--white);
}

.day-group {
  margin-top: 1.75rem;
  display: grid;
  gap: 0.75rem;
}

.day-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink-2);
}

.booking-card {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 1.25rem;
  padding: 1rem 1.25rem 1rem 1rem;
  border-radius: var(--radius-lg);
  background: var(--card);
  border: 1.5px solid transparent;
  transition:
    border-color 0.2s,
    box-shadow 0.2s,
    transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
  /* Kartu muncul bertahap saat daftar dimuat. */
  animation: muncul 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
  animation-delay: calc(var(--urutan, 0) * 60ms);
}

.booking-card:hover {
  box-shadow: var(--shadow);
  transform: translateY(-2px);
}

.booking-card.editing {
  border-color: var(--primary);
}

/* Sorotan sesaat untuk booking yang baru disimpan. */
.booking-card.sorot {
  animation: sorot 1.6s ease-out;
}

@keyframes muncul {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
}

@keyframes sorot {
  0%,
  35% {
    border-color: var(--primary);
    box-shadow: 0 0 0 6px var(--primary-soft);
  }
}

.time-tile {
  display: grid;
  place-items: center;
  align-content: center;
  width: 5.25rem;
  height: 5.25rem;
  border-radius: 20px;
  background: var(--primary-soft);
  color: var(--primary-strong);
  transition: background 0.2s;
}

.booking-card:hover .time-tile {
  background: rgb(43 122 105 / 0.18);
}

.time-tile strong {
  font-size: 1.3rem;
  letter-spacing: -0.02em;
}

.time-tile span {
  font-size: 0.75rem;
  font-weight: 600;
}

.info h4 {
  margin: 0;
  font-size: 1.05rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 1rem;
  margin: 0.35rem 0 0.55rem;
  color: var(--ink-2);
  font-size: 0.88rem;
}

.meta span {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.price {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--primary);
  white-space: nowrap;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.empty {
  display: grid;
  justify-items: center;
  gap: 0.75rem;
  margin-top: 1.75rem;
  padding: 3rem 1rem;
  border-radius: var(--radius-lg);
  border: 1.5px dashed var(--line);
  color: var(--muted);
  text-align: center;
}

.empty-icon {
  width: 2rem;
  height: 2rem;
}

/* ---------- Pop up form ---------- */
.form-modal {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  width: min(520px, 100%);
  max-height: calc(100vh - 2rem);
  padding: 1.5rem 1.75rem 1.75rem;
  border-radius: var(--radius-xl);
  background: var(--surface);
  box-shadow: var(--shadow);
  overflow-y: auto;
  scrollbar-width: thin;
}

.form-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.form-head h2 {
  font-size: 1.35rem;
  letter-spacing: -0.02em;
}

.court-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
}

.court {
  padding: 0.55rem 0;
  border-radius: 999px;
  border: 1.5px solid var(--ink-2);
  background: transparent;
  font-weight: 700;
  cursor: pointer;
  transition:
    border-color 0.15s,
    color 0.15s,
    background 0.15s,
    transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.court:hover {
  border-color: var(--primary);
  transform: translateY(-2px);
}

.court:active {
  transform: scale(0.92);
  transition-duration: 0.08s;
}

.court.selected {
  border-color: var(--primary);
  background: var(--primary-soft);
  color: var(--primary-strong);
  animation: pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pop {
  50% {
    transform: scale(1.1);
  }
}

.field {
  min-width: 0;
}

.label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
}

.durasi {
  padding: 0.2rem 0.75rem;
  border-radius: 8px;
  border: 1.5px solid var(--primary);
  color: var(--primary-strong);
  font-size: 0.82rem;
  font-weight: 700;
  white-space: nowrap;
}

.time-input .prefix {
  min-width: 3.6rem;
  font-size: 0.85rem;
}

.time-input input {
  padding: 0.7rem 0;
}

/* Tombol simpan menempel di bawah pop up saat isinya di-scroll. */
.form-footer {
  position: sticky;
  bottom: -1.75rem;
  margin: 0.25rem -1.75rem -1.75rem;
  padding: 0.75rem 1.75rem 1.75rem;
  background: linear-gradient(to bottom, rgb(235 233 253 / 0), var(--surface) 0.75rem);
}

/* Latar hijau bergeser ke opsi status yang dipilih. */
.segmented {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.25rem;
  padding: 0.35rem;
  border-radius: 999px;
  background: var(--card);
}

.segmented-indicator {
  position: absolute;
  top: 0.35rem;
  bottom: 0.35rem;
  left: 0.35rem;
  width: calc((100% - 0.7rem - 0.5rem) / 3);
  border-radius: 999px;
  background: var(--primary);
  transform: translateX(calc(var(--indeks, 0) * (100% + 0.25rem)));
  transition: transform 0.35s cubic-bezier(0.34, 1.3, 0.64, 1);
}

.segmented button {
  position: relative;
  padding: 0.65rem 0.25rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--primary);
  font-weight: 700;
  font-size: 0.88rem;
  white-space: nowrap;
  cursor: pointer;
  transition: color 0.25s;
}

.segmented button.active {
  color: var(--white);
}

/* ---------- Responsif ---------- */
@media (max-width: 640px) {
  .layout {
    padding: 1.25rem 1rem 3rem;
  }

  .booking-card {
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.75rem 1rem;
  }

  .time-tile {
    width: 4.25rem;
    height: 4.25rem;
  }

  .price {
    grid-column: 1;
    font-size: 1rem;
  }

  .actions {
    justify-content: flex-end;
  }

  /* Di ponsel pop up muncul dari bawah (bottom sheet). */
  .modal-backdrop:has(.form-modal) {
    place-items: end stretch;
    padding: 0;
  }

  .form-modal {
    width: 100%;
    max-height: 92vh;
    padding: 1.25rem;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  }

  .pop-enter-from .form-modal,
  .pop-leave-to .form-modal {
    transform: translateY(100%);
  }

  .form-footer {
    bottom: -1.25rem;
    margin: 0.25rem -1.25rem -1.25rem;
    padding: 0.75rem 1.25rem 1.25rem;
  }
}
</style>
