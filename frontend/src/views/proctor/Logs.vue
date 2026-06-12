<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">监考日志</h1>
      <div class="flex items-center gap-2">
        <button @click="showAddLogModal = true" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700">
          + 添加日志
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">总计</div>
        <div class="text-2xl font-bold">{{ logStats.total || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">普通</div>
        <div class="text-2xl font-bold text-green-600">{{ logStats.normal || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">警告</div>
        <div class="text-2xl font-bold text-yellow-600">{{ logStats.warning || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">严重</div>
        <div class="text-2xl font-bold text-red-600">{{ logStats.danger || 0 }}</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <select v-model="filters.exam_paper_id" @change="loadLogs"
          class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部试卷</option>
          <option v-for="p in examPapers" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
        <select v-model="filters.log_type" @change="loadLogs"
          class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部类型</option>
          <option value="checkin">签到</option>
          <option value="seat_change">换座</option>
          <option value="suspicious">异常</option>
          <option value="verification">核验</option>
          <option value="other">其他</option>
        </select>
        <select v-model="filters.severity" @change="loadLogs"
          class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部级别</option>
          <option value="normal">普通</option>
          <option value="warning">警告</option>
          <option value="danger">严重</option>
        </select>
        <div class="flex gap-2">
          <input v-model="filters.keyword" type="text" placeholder="搜索学生/座位/内容"
            class="flex-1 border border-gray-300 rounded-md px-3 py-2"
            @keyup.enter="loadLogs">
          <button @click="loadLogs" class="bg-gray-100 text-gray-700 px-4 rounded hover:bg-gray-200">搜索</button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      </div>
      <div v-else-if="logs.length === 0" class="text-center py-8 text-gray-500">
        暂无日志记录
      </div>
      <div v-else class="space-y-3">
        <div v-for="log in logs" :key="log.id"
          class="border-l-4 rounded-r p-4"
          :class="{
            'border-red-500 bg-red-50': log.severity === 'danger',
            'border-yellow-500 bg-yellow-50': log.severity === 'warning',
            'border-green-500 bg-green-50': log.severity === 'normal',
          }">
          <div class="flex justify-between items-start">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs px-2 py-0.5 rounded-full"
                :class="{
                  'bg-red-200 text-red-900 font-semibold': log.severity === 'danger',
                  'bg-yellow-200 text-yellow-900 font-semibold': log.severity === 'warning',
                  'bg-green-200 text-green-900': log.severity === 'normal',
                }">{{ log.severity_label }}</span>
              <span class="text-xs px-2 py-0.5 rounded bg-white text-gray-700">{{ log.log_type_label }}</span>
              <span v-if="log.exam_paper" class="text-xs text-gray-600">
                📝 {{ log.exam_paper.title }}
              </span>
              <span v-if="log.exam_seat" class="text-xs text-gray-600">
                🪑 {{ log.exam_seat.exam_room?.name }} - {{ log.exam_seat.seat_number }} ({{ log.exam_seat.computer_code }})
              </span>
              <span v-if="log.user" class="text-xs text-gray-600">
                👤 {{ log.user.real_name }}
              </span>
            </div>
            <span class="text-xs text-gray-400 whitespace-nowrap ml-4">
              {{ new Date(log.created_at).toLocaleString() }}
            </span>
          </div>
          <div class="text-sm mt-3 text-gray-800 leading-relaxed">
            {{ log.content }}
          </div>
          <div class="text-xs text-gray-400 mt-2 flex justify-between">
            <span>操作人：{{ log.operator?.real_name || '系统' }}</span>
            <span v-if="log.operator_ip">IP: {{ log.operator_ip }}</span>
          </div>
        </div>
      </div>
    </div>

    <Modal v-if="showAddLogModal">
      <h3 class="text-lg font-semibold mb-4">添加监考日志</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">选择试卷 *</label>
          <select v-model="addLogForm.exam_paper_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="p in examPapers" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">座位</label>
            <select v-model="addLogForm.exam_seat_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
              <option value="">不关联</option>
              <option v-for="s in allSeats" :key="s.id" :value="s.id">
                {{ s.exam_room?.name }} - {{ s.seat_number }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">学生</label>
            <select v-model="addLogForm.user_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
              <option value="">不关联</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">日志类型 *</label>
            <select v-model="addLogForm.log_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
              <option value="checkin">签到</option>
              <option value="seat_change">换座</option>
              <option value="suspicious">异常</option>
              <option value="verification">核验</option>
              <option value="other">其他</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">级别 *</label>
            <select v-model="addLogForm.severity" class="w-full border border-gray-300 rounded-md px-3 py-2">
              <option value="normal">普通</option>
              <option value="warning">警告</option>
              <option value="danger">严重</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">日志内容 *</label>
          <textarea v-model="addLogForm.content" rows="3"
            placeholder="请详细描述..."
            class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button @click="showAddLogModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
        <button @click="submitAddLog" :disabled="saving"
          class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? '保存中...' : '保存' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../../api'
import { useModal } from '../../composables/useModal'
import Modal from '../../components/Modal.vue'

const { alert } = useModal()

const logs = ref([])
const loading = ref(false)
const examPapers = ref([])
const allSeats = ref([])
const logStats = ref({ total: 0, normal: 0, warning: 0, danger: 0 })

const filters = ref({
  exam_paper_id: '',
  log_type: '',
  severity: '',
  keyword: '',
})

const showAddLogModal = ref(false)
const saving = ref(false)
const addLogForm = ref({
  exam_paper_id: '',
  exam_seat_id: '',
  user_id: '',
  log_type: 'other',
  severity: 'normal',
  content: '',
})

const loadLogs = async () => {
  loading.value = true
  try {
    const params = {}
    Object.entries(filters.value).forEach(([k, v]) => {
      if (v) params[k] = v
    })
    const res = await api.get('/proctor/logs', { params })
    logs.value = res.data.logs.data || []
    logStats.value = res.data.stats || logStats.value
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const loadBaseData = async () => {
  try {
    const [p, r] = await Promise.all([
      api.get('/exam-papers', { params: { per_page: 100 } }),
      api.get('/exam-rooms/all'),
    ])
    examPapers.value = p.data.exam_papers?.data || []

    const roomIds = (r.data.rooms || []).map(room => room.id)
    const seatPromises = roomIds.map(id => api.get(`/exam-rooms/${id}/seats`))
    const seatResults = await Promise.allSettled(seatPromises)
    const seatsList = []
    seatResults.forEach((result, idx) => {
      if (result.status === 'fulfilled') {
        const room = r.data.rooms[idx]
        result.value.data.seats.forEach(s => {
          s.exam_room = { name: room.name, code: room.code }
          seatsList.push(s)
        })
      }
    })
    allSeats.value = seatsList
  } catch (e) {
    console.error(e)
  }
}

const submitAddLog = async () => {
  if (!addLogForm.value.exam_paper_id || !addLogForm.value.content) {
    alert('请填写试卷和内容', '校验', 'warning')
    return
  }
  saving.value = true
  try {
    const data = { ...addLogForm.value }
    if (!data.exam_seat_id) delete data.exam_seat_id
    if (!data.user_id) delete data.user_id
    await api.post('/proctor/logs', data)
    alert('日志记录成功', '操作成功', 'success')
    showAddLogModal.value = false
    addLogForm.value = {
      exam_paper_id: '',
      exam_seat_id: '',
      user_id: '',
      log_type: 'other',
      severity: 'normal',
      content: '',
    }
    loadLogs()
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '失败', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadBaseData()
  await loadLogs()
})
</script>
