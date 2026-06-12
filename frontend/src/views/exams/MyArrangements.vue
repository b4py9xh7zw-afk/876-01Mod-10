<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">我的考试安排</h1>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="arrangements.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
      <div class="text-6xl mb-4">📋</div>
      <h3 class="text-xl font-semibold text-gray-700 mb-2">暂无考试安排</h3>
      <p class="text-gray-500">请联系教务老师安排考试座位</p>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="a in arrangements" :key="a.id" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4" :class="headerClass(a.status)">
          <div class="flex justify-between items-start">
            <div class="text-white">
              <h3 class="text-lg font-semibold truncate max-w-xs">{{ a.exam_paper?.title }}</h3>
              <p class="text-sm opacity-80 mt-1">{{ a.exam_paper?.total_time }} 分钟 · {{ a.exam_paper?.total_score }} 分</p>
            </div>
            <span class="bg-white/30 text-white px-3 py-1 rounded-full text-sm font-semibold whitespace-nowrap">
              {{ statusLabel(a.status) }}
            </span>
          </div>
        </div>

        <div class="p-6 space-y-4">
          <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-500 mb-3">📍 考场位置</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-gray-600">机房</span>
                <span class="font-medium">{{ a.exam_seat?.exam_room?.name || '-' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">位置</span>
                <span class="font-medium">{{ a.exam_seat?.exam_room?.location || '-' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">座位号</span>
                <span class="font-bold text-xl text-indigo-600">{{ a.exam_seat?.seat_number || '-' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">电脑编号</span>
                <span class="font-mono text-sm">{{ a.exam_seat?.computer_code || '-' }}</span>
              </div>
            </div>
          </div>

          <div class="bg-yellow-50 rounded-lg p-4" v-if="a.status === 'assigned'">
            <h4 class="text-sm font-medium text-yellow-700 mb-2">🎫 签到码</h4>
            <div class="text-center">
              <div class="text-3xl font-mono font-bold tracking-widest text-yellow-800 select-all bg-white rounded py-2 mb-2">
                {{ a.checkin_code }}
              </div>
              <p class="text-xs text-yellow-600">请提前到达机房，使用签到码签到</p>
            </div>
          </div>

          <div class="bg-blue-50 rounded-lg p-4" v-if="a.status === 'checked_in'">
            <h4 class="text-sm font-medium text-blue-700 mb-2">✅ 已签到</h4>
            <div class="text-sm text-blue-600">
              签到时间：{{ a.checkin_time ? new Date(a.checkin_time).toLocaleString() : '-' }}
            </div>
            <p class="text-xs text-blue-500 mt-2">请确认电脑编号为 {{ a.exam_seat?.computer_code }} 后开始考试</p>
          </div>

          <div class="bg-green-50 rounded-lg p-4" v-if="a.status === 'submitted'">
            <h4 class="text-sm font-medium text-green-700 mb-2">🎉 已交卷</h4>
            <p class="text-xs text-green-600">可在「考试记录」中查看成绩</p>
          </div>

          <div class="space-y-2 pt-2">
            <div v-if="a.status === 'assigned'" class="w-full">
              <button @click="showCheckinModal(a)"
                class="w-full bg-yellow-500 text-white py-3 rounded-lg font-semibold hover:bg-yellow-600">
                输入签到码签到
              </button>
            </div>
            <button v-if="a.status === 'checked_in'"
              @click="goToExam(a)"
              class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700">
              进入考试 →
            </button>
            <button v-if="a.status === 'examining'"
              @click="goToExam(a)"
              class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700">
              继续考试 →
            </button>
          </div>
        </div>
      </div>
    </div>

    <Modal v-if="showCheckin">
      <h3 class="text-lg font-semibold mb-4">自助签到</h3>
      <div v-if="currentArrangement" class="mb-4 text-sm text-gray-600 bg-gray-50 p-3 rounded">
        <p class="font-medium mb-1">{{ currentArrangement.exam_paper?.title }}</p>
        <p>座位：{{ currentArrangement.exam_seat?.exam_room?.name }} - {{ currentArrangement.exam_seat?.seat_number }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">输入签到码</label>
        <input v-model="checkinCode" type="text"
          placeholder="请输入签到码，如：ABCD1234"
          class="w-full border border-gray-300 rounded-md px-4 py-3 text-center text-xl font-mono tracking-widest focus:ring-indigo-500 focus:border-indigo-500"
          @keyup.enter="doCheckin">
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button @click="showCheckin = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
        <button @click="doCheckin" :disabled="checking"
          class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
          {{ checking ? '签到中...' : '确认签到' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'
import Modal from '../../components/Modal.vue'

const router = useRouter()
const { alert } = useModal()

const arrangements = ref([])
const loading = ref(false)

const showCheckin = ref(false)
const checking = ref(false)
const checkinCode = ref('')
const currentArrangement = ref(null)

const statusLabel = (s) => ({
  assigned: '待签到',
  checked_in: '已签到',
  examining: '考试中',
  submitted: '已交卷',
  absent: '缺考',
}[s] || s)

const headerClass = (s) => ({
  'bg-gray-500': s === 'assigned',
  'bg-green-600': s === 'checked_in',
  'bg-blue-600': s === 'examining',
  'bg-purple-600': s === 'submitted',
  'bg-red-500': s === 'absent',
}[s] || 'bg-gray-500')

const loadArrangements = async () => {
  loading.value = true
  try {
    const res = await api.get('/exam-arrangements/my')
    arrangements.value = res.data.arrangements.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const showCheckinModal = (a) => {
  currentArrangement.value = a
  checkinCode.value = ''
  showCheckin.value = true
}

const doCheckin = async () => {
  if (!checkinCode.value.trim()) {
    alert('请输入签到码', '校验', 'warning')
    return
  }
  checking.value = true
  try {
    await api.post('/exam-arrangements/self-checkin', {
      checkin_code: checkinCode.value.trim().toUpperCase(),
    })
    alert('签到成功！请确认电脑编号后开始考试', '签到成功', 'success')
    showCheckin.value = false
    checkinCode.value = ''
    loadArrangements()
  } catch (e) {
    alert(e.response?.data?.message || '签到失败', '失败', 'error')
  } finally {
    checking.value = false
  }
}

const goToExam = (a) => {
  router.push(`/exams/${a.exam_paper_id}`)
}

onMounted(loadArrangements)
</script>
