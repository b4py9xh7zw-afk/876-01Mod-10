<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">考试座位安排</h1>
      <div class="space-x-2">
        <button @click="showAssignModal = true" class="bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700">
          + 单个安排
        </button>
        <button @click="showImportModal = true" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700">
          批量导入
        </button>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <select v-model="filters.exam_paper_id" @change="loadArrangements"
          class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部试卷</option>
          <option v-for="p in examPapers" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
        <select v-model="filters.exam_room_id" @change="loadArrangements"
          class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部机房</option>
          <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}({{ r.code }})</option>
        </select>
        <select v-model="filters.status" @change="loadArrangements"
          class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部状态</option>
          <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
        </select>
        <div class="flex gap-2">
          <input v-model="filters.keyword" type="text" placeholder="搜索学生/座位"
            class="flex-1 border border-gray-300 rounded-md px-3 py-2"
            @keyup.enter="loadArrangements">
          <button @click="loadArrangements" class="bg-gray-100 text-gray-700 px-4 rounded hover:bg-gray-200">搜索</button>
        </div>
      </div>

      <div v-if="filters.exam_paper_id" class="bg-blue-50 rounded-lg p-3 mb-4 text-sm">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div><span class="text-gray-500">总安排:</span> <b>{{ overview.total_arrangements || 0 }}</b></div>
          <div><span class="text-gray-500">未签到:</span> <b class="text-gray-600">{{ overview.assigned || 0 }}</b></div>
          <div><span class="text-gray-500">已签到:</span> <b class="text-green-600">{{ overview.checked_in || 0 }}</b></div>
          <div><span class="text-gray-500">考试中:</span> <b class="text-blue-600">{{ overview.examining || 0 }}</b></div>
          <div><span class="text-gray-500">签到率:</span> <b>{{ overview.checkin_rate || 0 }}%</b></div>
          <div><span class="text-gray-500">已交卷:</span> <b class="text-gray-700">{{ overview.submitted || 0 }}</b></div>
          <div><span class="text-gray-500">异常记录:</span> <b class="text-orange-600">{{ overview.anomaly_logs || 0 }}</b></div>
          <div><span class="text-gray-500">换座次数:</span> <b class="text-yellow-600">{{ overview.seat_changes || 0 }}</b></div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      </div>
      <div v-else-if="arrangements.length === 0" class="text-center py-8 text-gray-500">
        暂无安排数据
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">试卷</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">机房/座位</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">电脑编号</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">学生信息</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">签到码</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">签到时间</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="a in arrangements" :key="a.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">{{ a.exam_paper?.title || '-' }}</td>
              <td class="px-4 py-3">
                <div>{{ a.exam_seat?.exam_room?.name || '-' }}</div>
                <div class="text-xs text-gray-500">座位 {{ a.exam_seat?.seat_number || '-' }}</div>
              </td>
              <td class="px-4 py-3 font-mono text-xs">{{ a.exam_seat?.computer_code || '-' }}</td>
              <td class="px-4 py-3">
                <div>{{ a.user?.real_name || '-' }}</div>
                <div class="text-xs text-gray-500">{{ a.user?.username || '-' }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="font-mono text-xs bg-yellow-50 text-yellow-800 px-2 py-1 rounded">{{ a.checkin_code }}</span>
              </td>
              <td class="px-4 py-3 text-xs text-gray-500">
                {{ a.checkin_time ? new Date(a.checkin_time).toLocaleString() : '-' }}
              </td>
              <td class="px-4 py-3">
                <span :class="statusClass(a.status)" class="px-2 inline-flex text-xs font-semibold rounded-full">
                  {{ statusOptions[a.status] || a.status }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap font-medium space-x-2">
                <button v-if="a.status === 'assigned'" @click="checkin(a)"
                  class="text-green-600 hover:text-green-900">签到</button>
                <button @click="changeSeat(a)" class="text-blue-600 hover:text-blue-900">换座</button>
                <button v-if="a.status === 'assigned'" @click="removeArrangement(a)"
                  class="text-red-600 hover:text-red-900">取消</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showAssignModal">
      <h3 class="text-lg font-semibold mb-4">安排考试座位</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">选择试卷 *</label>
          <select v-model="assignForm.exam_paper_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="p in examPapers" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">选择机房 *</label>
          <select v-model="assignForm.exam_room_id" @change="loadAvailableSeats"
            class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}({{ r.code }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">选择座位 *</label>
          <select v-model="assignForm.exam_seat_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="s in availableSeats" :key="s.id" :value="s.id">
              {{ s.seat_number }} - {{ s.computer_code }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">选择学生 *</label>
          <select v-model="assignForm.user_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="u in students" :key="u.id" :value="u.id">
              {{ u.real_name }} ({{ u.username }})
            </option>
          </select>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button @click="showAssignModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
        <button @click="submitAssign" :disabled="saving"
          class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? '保存中...' : '保存' }}
        </button>
      </div>
    </Modal>

    <Modal v-if="showImportModal" size="lg">
      <h3 class="text-lg font-semibold mb-4">批量导入座位安排</h3>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">选择试卷 *</label>
            <select v-model="importForm.exam_paper_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
              <option value="">请选择</option>
              <option v-for="p in examPapers" :key="p.id" :value="p.id">{{ p.title }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">选择机房 *</label>
            <select v-model="importForm.exam_room_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
              <option value="">请选择</option>
              <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}({{ r.code }})</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">导入数据 *</label>
          <p class="text-xs text-gray-500 mb-1">每行一条：座位号,学号/用户名/邮箱</p>
          <textarea v-model="importText" rows="8"
            placeholder="A01,20230001&#10;A02,zhangsan@example.com&#10;B01,lisi"
            class="w-full border border-gray-300 rounded-md px-3 py-2 font-mono text-sm"></textarea>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button @click="showImportModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
        <button @click="submitImport" :disabled="saving"
          class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? '导入中...' : '导入' }}
        </button>
      </div>
    </Modal>

    <Modal v-if="showChangeSeatModal">
      <h3 class="text-lg font-semibold mb-2">换座登记</h3>
      <div v-if="currentArrangement" class="text-sm text-gray-500 mb-4 bg-gray-50 p-3 rounded">
        <div>学生：{{ currentArrangement.user?.real_name }} ({{ currentArrangement.user?.username }})</div>
        <div>当前座位：{{ currentArrangement.exam_seat?.exam_room?.name }} - {{ currentArrangement.exam_seat?.seat_number }} ({{ currentArrangement.exam_seat?.computer_code }})</div>
      </div>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">目标机房</label>
          <select v-model="changeSeatForm.exam_room_id" @change="loadAvailableSeatsForChange"
            class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}({{ r.code }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">目标座位 *</label>
          <select v-model="changeSeatForm.new_seat_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
            <option value="">请选择</option>
            <option v-for="s in availableSeatsForChange" :key="s.id" :value="s.id">
              {{ s.seat_number }} - {{ s.computer_code }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">换座原因 *</label>
          <textarea v-model="changeSeatForm.reason" rows="3" placeholder="请详细说明换座原因"
            class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button @click="showChangeSeatModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
        <button @click="submitChangeSeat" :disabled="saving"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
          {{ saving ? '提交中...' : '确认换座' }}
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

const { alert, confirm } = useModal()

const arrangements = ref([])
const loading = ref(false)
const rooms = ref([])
const examPapers = ref([])
const students = ref([])
const availableSeats = ref([])
const availableSeatsForChange = ref([])
const overview = ref({})

const filters = ref({
  exam_paper_id: '',
  exam_room_id: '',
  status: '',
  keyword: '',
})

const statusOptions = {
  assigned: '已安排',
  checked_in: '已签到',
  examining: '考试中',
  submitted: '已交卷',
  absent: '缺考',
}

const statusClass = (s) => ({
  'bg-gray-100 text-gray-800': s === 'assigned',
  'bg-green-100 text-green-800': s === 'checked_in',
  'bg-blue-100 text-blue-800': s === 'examining',
  'bg-purple-100 text-purple-800': s === 'submitted',
  'bg-red-100 text-red-800': s === 'absent',
})

const showAssignModal = ref(false)
const assignForm = ref({
  exam_paper_id: '',
  exam_room_id: '',
  exam_seat_id: '',
  user_id: '',
})

const showImportModal = ref(false)
const importForm = ref({
  exam_paper_id: '',
  exam_room_id: '',
})
const importText = ref('')

const showChangeSeatModal = ref(false)
const currentArrangement = ref(null)
const changeSeatForm = ref({
  arrangement_id: '',
  new_seat_id: '',
  exam_room_id: '',
  reason: '',
})

const saving = ref(false)

const loadArrangements = async () => {
  loading.value = true
  try {
    const params = {}
    Object.entries(filters.value).forEach(([k, v]) => {
      if (v) params[k] = v
    })
    const res = await api.get('/exam-arrangements', { params })
    arrangements.value = res.data.arrangements.data || []

    if (filters.value.exam_paper_id) {
      const ov = await api.get('/proctor/overview', {
        params: { exam_paper_id: filters.value.exam_paper_id },
      })
      overview.value = ov.data.overview || {}
    } else {
      overview.value = {}
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const loadBaseData = async () => {
  try {
    const [r, p] = await Promise.all([
      api.get('/exam-rooms/all'),
      api.get('/exam-papers', { params: { per_page: 100 } }),
    ])
    rooms.value = r.data.rooms || []
    examPapers.value = p.data.exam_papers?.data || []
  } catch (e) {
    console.error(e)
  }
}

const loadStudents = async () => {
  try {
    const res = await api.get('/auth/students')
    students.value = res.data.students || []
  } catch (e) {
    console.error(e)
  }
}

const loadAvailableSeats = async () => {
  if (!assignForm.value.exam_room_id || !assignForm.value.exam_paper_id) return
  try {
    const res = await api.get(`/exam-rooms/${assignForm.value.exam_room_id}/seats`)
    const allSeats = res.data.seats || []
    const ar = await api.get('/exam-arrangements', {
      params: { exam_paper_id: assignForm.value.exam_paper_id, per_page: 1000 },
    })
    const usedSeatIds = (ar.data.arrangements.data || [])
      .filter(a => ['assigned', 'checked_in', 'examining'].includes(a.status))
      .map(a => a.exam_seat_id)
    availableSeats.value = allSeats.filter(s => !usedSeatIds.includes(s.id))
  } catch (e) {
    console.error(e)
  }
}

const loadAvailableSeatsForChange = async () => {
  if (!changeSeatForm.value.exam_room_id || !currentArrangement.value) return
  try {
    const res = await api.get(`/exam-rooms/${changeSeatForm.value.exam_room_id}/seats`)
    const allSeats = res.data.seats || []
    const ar = await api.get('/exam-arrangements', {
      params: { exam_paper_id: currentArrangement.value.exam_paper_id, per_page: 1000 },
    })
    const usedSeatIds = (ar.data.arrangements.data || [])
      .filter(a => a.id !== currentArrangement.value.id && ['assigned', 'checked_in', 'examining'].includes(a.status))
      .map(a => a.exam_seat_id)
    availableSeatsForChange.value = allSeats.filter(s => !usedSeatIds.includes(s.id))
  } catch (e) {
    console.error(e)
  }
}

const submitAssign = async () => {
  if (!assignForm.value.exam_paper_id || !assignForm.value.exam_seat_id || !assignForm.value.user_id) {
    alert('请完整填写', '校验', 'warning')
    return
  }
  saving.value = true
  try {
    await api.post('/exam-arrangements', assignForm.value)
    alert('安排成功', '操作成功', 'success')
    showAssignModal.value = false
    assignForm.value = { exam_paper_id: '', exam_room_id: '', exam_seat_id: '', user_id: '' }
    loadArrangements()
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '失败', 'error')
  } finally {
    saving.value = false
  }
}

const submitImport = async () => {
  if (!importForm.value.exam_paper_id || !importForm.value.exam_room_id || !importText.value.trim()) {
    alert('请完整填写', '校验', 'warning')
    return
  }
  saving.value = true
  try {
    const lines = importText.value.trim().split('\n').filter(l => l.trim())
    const arrangementsData = lines.map(line => {
      const parts = line.split(/[,，\t]/).map(p => p.trim())
      return {
        seat_number: parts[0],
        user_identifier: parts[1],
      }
    }).filter(a => a.seat_number && a.user_identifier)

    const res = await api.post('/exam-arrangements/import', {
      ...importForm.value,
      arrangements: arrangementsData,
    })
    alert(res.data.message, '导入完成', res.data.errors?.length ? 'warning' : 'success')
    showImportModal.value = false
    importForm.value = { exam_paper_id: '', exam_room_id: '' }
    importText.value = ''
    loadArrangements()
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '失败', 'error')
  } finally {
    saving.value = false
  }
}

const checkin = async (a) => {
  const ok = await confirm(`确认签到学生「${a.user?.real_name}」吗？`, '签到确认', 'info')
  if (!ok) return
  try {
    await api.post('/exam-arrangements/checkin', { arrangement_id: a.id })
    alert('签到成功', '操作成功', 'success')
    loadArrangements()
  } catch (e) {
    alert(e.response?.data?.message || '签到失败', '失败', 'error')
  }
}

const changeSeat = (a) => {
  currentArrangement.value = a
  changeSeatForm.value = {
    arrangement_id: a.id,
    new_seat_id: '',
    exam_room_id: a.exam_seat?.exam_room?.id || '',
    reason: '',
  }
  availableSeatsForChange.value = []
  showChangeSeatModal.value = true
  loadAvailableSeatsForChange()
}

const submitChangeSeat = async () => {
  if (!changeSeatForm.value.new_seat_id || !changeSeatForm.value.reason) {
    alert('请选择目标座位并填写原因', '校验', 'warning')
    return
  }
  saving.value = true
  try {
    await api.post('/exam-arrangements/change-seat', {
      arrangement_id: changeSeatForm.value.arrangement_id,
      new_seat_id: changeSeatForm.value.new_seat_id,
      reason: changeSeatForm.value.reason,
    })
    alert('换座成功，已记录监考日志', '操作成功', 'success')
    showChangeSeatModal.value = false
    loadArrangements()
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '失败', 'error')
  } finally {
    saving.value = false
  }
}

const removeArrangement = async (a) => {
  const ok = await confirm(`确定取消「${a.user?.real_name}」的座位安排吗？`, '删除确认', 'warning')
  if (!ok) return
  try {
    await api.delete(`/exam-arrangements/${a.id}`)
    alert('取消成功', '操作成功', 'success')
    loadArrangements()
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '失败', 'error')
  }
}

onMounted(async () => {
  await loadBaseData()
  await loadStudents()
  await loadArrangements()
})
</script>
