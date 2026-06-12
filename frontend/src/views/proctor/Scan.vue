<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">巡考 - 扫码查座</h1>
      <div class="space-x-2">
        <select v-model="currentExamId" @change="loadOverview"
          class="border border-gray-300 rounded-md px-3 py-2 text-sm">
          <option value="">选择考试</option>
          <option v-for="p in examPapers" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
      </div>
    </div>

    <div v-if="currentExamId" class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">总安排</div>
        <div class="text-2xl font-bold">{{ stats.total_arrangements || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">已签到</div>
        <div class="text-2xl font-bold text-green-600">{{ stats.checked_in || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">考试中</div>
        <div class="text-2xl font-bold text-blue-600">{{ stats.examining || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">已交卷</div>
        <div class="text-2xl font-bold text-purple-600">{{ stats.submitted || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-500 text-sm">异常记录</div>
        <div class="text-2xl font-bold text-red-600">{{ stats.anomaly_logs || 0 }}</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">输入/扫描二维码Token</h3>
      <div class="flex gap-3">
        <input v-model="qrToken" type="text" placeholder="输入座位二维码Token"
          class="flex-1 border border-gray-300 rounded-md px-4 py-3 focus:ring-indigo-500 focus:border-indigo-500"
          @keyup.enter="scanSeat">
        <button @click="scanSeat" :disabled="scanning || !qrToken"
          class="bg-indigo-600 text-white px-6 py-3 rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ scanning ? '查询中...' : '查询座位' }}
        </button>
      </div>
      <div class="mt-3 text-sm text-gray-500">
        <p>💡 提示：巡考老师扫描座位上的二维码后，会获取到二维码Token，在此输入即可查询该座位情况。</p>
      </div>
    </div>

    <div v-if="seatInfo" class="space-y-6">
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-indigo-600 text-white px-6 py-4">
          <div class="flex justify-between items-center">
            <h3 class="text-xl font-semibold">座位信息</h3>
            <span class="bg-white/20 px-3 py-1 rounded-full text-sm">
              {{ seatInfo.seat.exam_room?.name }} · {{ seatInfo.seat.exam_room?.code }}
            </span>
          </div>
        </div>
        <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-6">
          <div>
            <div class="text-sm text-gray-500">座位号</div>
            <div class="text-3xl font-bold text-indigo-600 mt-1">{{ seatInfo.seat.seat_number }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500">电脑编号</div>
            <div class="text-xl font-mono mt-1">{{ seatInfo.seat.computer_code }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500">位置</div>
            <div class="text-lg mt-1">
              {{ seatInfo.seat.exam_room?.location || '-' }}
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500">行列</div>
            <div class="text-lg mt-1">{{ (seatInfo.seat.row_no || '-') }}-{{ (seatInfo.seat.col_no || '-') }}</div>
          </div>
        </div>
      </div>

      <div v-if="seatInfo.arrangement" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-green-600 text-white px-6 py-4 flex justify-between items-center">
          <h3 class="text-xl font-semibold">学生身份信息</h3>
          <span :class="arrangementStatusClass(seatInfo.arrangement.status)" class="px-3 py-1 rounded-full text-sm font-semibold">
            {{ seatInfo.arrangement.status_label }}
          </span>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
            <div>
              <div class="text-sm text-gray-500">姓名</div>
              <div class="text-2xl font-bold mt-1">{{ seatInfo.arrangement.student?.real_name }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-500">学号/用户名</div>
              <div class="text-xl font-mono mt-1">{{ seatInfo.arrangement.student?.username }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-500">邮箱</div>
              <div class="text-lg mt-1">{{ seatInfo.arrangement.student?.email || '-' }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-500">考试</div>
              <div class="text-lg mt-1">{{ seatInfo.arrangement.exam_paper?.title }}</div>
            </div>
          </div>

          <div class="border-t pt-4">
            <div class="grid grid-cols-2 gap-6">
              <div>
                <div class="text-sm text-gray-500">签到码</div>
                <div class="font-mono bg-yellow-50 text-yellow-800 px-3 py-2 rounded inline-block mt-1">
                  {{ seatInfo.arrangement.checkin_code }}
                </div>
              </div>
              <div>
                <div class="text-sm text-gray-500">签到时间</div>
                <div class="text-lg mt-1">
                  {{ seatInfo.arrangement.checkin_time ? new Date(seatInfo.arrangement.checkin_time).toLocaleString() : '未签到' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <div class="flex items-center">
          <div class="text-4xl mr-4">⚠️</div>
          <div>
            <div class="font-semibold text-yellow-800">该座位暂无考试安排</div>
            <div class="text-sm text-yellow-700 mt-1">此座位当前没有安排学生考试</div>
          </div>
        </div>
      </div>

      <div v-if="seatInfo.arrangement" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
          <h3 class="text-xl font-semibold">考试进度</h3>
          <div class="text-sm">
            <span v-if="seatInfo.exam_progress" class="bg-white/20 px-3 py-1 rounded-full">
              {{ seatInfo.exam_progress.status_label }}
            </span>
          </div>
        </div>
        <div class="p-6">
          <div v-if="seatInfo.exam_progress" class="space-y-4">
            <div v-if="seatInfo.exam_progress.progress_percent !== undefined">
              <div class="flex justify-between text-sm mb-1">
                <span class="text-gray-500">进度</span>
                <span class="font-semibold">{{ seatInfo.exam_progress.progress_percent }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-4">
                <div class="bg-blue-600 h-4 rounded-full transition-all"
                  :style="{ width: seatInfo.exam_progress.progress_percent + '%' }"></div>
              </div>
              <div class="flex justify-between text-sm text-gray-500 mt-2">
                <span>开始: {{ new Date(seatInfo.exam_progress.start_time).toLocaleTimeString() }}</span>
                <span>剩余: {{ formatDuration(seatInfo.exam_progress.remaining_seconds) }}</span>
              </div>
            </div>
            <div v-else-if="seatInfo.exam_progress.status === 'graded' || seatInfo.exam_progress.status === 'submitted'">
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-green-50 rounded-lg p-4">
                  <div class="text-sm text-gray-500">得分</div>
                  <div class="text-3xl font-bold text-green-600 mt-1">{{ seatInfo.exam_progress.score ?? '-' }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                  <div class="text-sm text-gray-500">交卷时间</div>
                  <div class="text-lg mt-1">
                    {{ seatInfo.exam_progress.end_time ? new Date(seatInfo.exam_progress.end_time).toLocaleString() : '-' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-gray-500 text-center py-4">
            考试尚未开始
          </div>
        </div>
      </div>

      <div v-if="seatInfo.arrangement" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center" :class="seatInfo.anomaly_count > 0 ? 'bg-red-600 text-white' : 'bg-gray-50'">
          <h3 class="text-xl font-semibold">
            {{ seatInfo.anomaly_count > 0 ? `异常记录 (${seatInfo.anomaly_count}条)` : '换座历史' }}
          </h3>
        </div>
        <div class="p-6">
          <div v-if="seatInfo.seat_change_history?.length > 0" class="space-y-3 mb-6">
            <h4 class="font-medium text-gray-700 mb-2">换座历史</h4>
            <div v-for="(h, idx) in seatInfo.seat_change_history" :key="idx"
              class="border-l-4 border-yellow-400 bg-yellow-50 p-4 rounded-r">
              <div class="flex justify-between">
                <div>
                  <span class="font-medium">{{ h.old_seat }}</span>
                  <span class="mx-2 text-yellow-600">→</span>
                  <span class="font-medium">{{ h.new_seat }}</span>
                </div>
                <span class="text-sm text-gray-500">{{ new Date(h.created_at).toLocaleString() }}</span>
              </div>
              <div class="text-sm mt-2">
                <span class="text-gray-500">原因：</span>{{ h.reason }}
              </div>
              <div class="text-sm text-gray-500">操作人：{{ h.operator }}</div>
            </div>
          </div>

          <div v-if="seatInfo.anomaly_logs?.length > 0">
            <h4 class="font-medium text-gray-700 mb-3">监考日志 (最近20条)</h4>
            <div class="space-y-2 max-h-96 overflow-y-auto">
              <div v-for="log in seatInfo.anomaly_logs" :key="log.id"
                class="p-3 rounded border-l-4"
                :class="{
                  'border-red-500 bg-red-50': log.severity === 'danger',
                  'border-yellow-500 bg-yellow-50': log.severity === 'warning',
                  'border-green-500 bg-green-50': log.severity === 'normal',
                }">
                <div class="flex justify-between items-start">
                  <div>
                    <span class="text-xs px-2 py-0.5 rounded-full mr-2"
                      :class="{
                        'bg-red-100 text-red-800': log.severity === 'danger',
                        'bg-yellow-100 text-yellow-800': log.severity === 'warning',
                        'bg-green-100 text-green-800': log.severity === 'normal',
                      }">{{ log.severity_label }}</span>
                    <span class="text-xs text-gray-500">{{ log.log_type_label }}</span>
                  </div>
                  <span class="text-xs text-gray-400">{{ new Date(log.created_at).toLocaleTimeString() }}</span>
                </div>
                <div class="text-sm mt-2 text-gray-700">{{ log.content }}</div>
                <div class="text-xs text-gray-400 mt-1">操作人：{{ log.operator || '系统' }}</div>
              </div>
            </div>
          </div>
          <div v-else class="text-gray-400 text-center py-4">暂无相关日志记录</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useModal } from '../../composables/useModal'

const { alert } = useModal()

const qrToken = ref('')
const scanning = ref(false)
const seatInfo = ref(null)
const examPapers = ref([])
const currentExamId = ref('')
const stats = ref({})

const arrangementStatusClass = (s) => ({
  'bg-gray-200 text-gray-800': s === 'assigned',
  'bg-green-100 text-green-800': s === 'checked_in',
  'bg-blue-100 text-blue-800': s === 'examining',
  'bg-purple-100 text-purple-800': s === 'submitted',
  'bg-red-100 text-red-800': s === 'absent',
})

const formatDuration = (seconds) => {
  if (!seconds && seconds !== 0) return '-'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const scanSeat = async () => {
  if (!qrToken.value.trim()) return
  scanning.value = true
  seatInfo.value = null
  try {
    const data = {}
    if (currentExamId.value) data.exam_paper_id = currentExamId.value
    const res = await api.post('/proctor/scan-seat', {
      qr_token: qrToken.value.trim(),
      ...data,
    })
    seatInfo.value = res.data
  } catch (e) {
    alert(e.response?.data?.message || '查询失败', '错误', 'error')
  } finally {
    scanning.value = false
  }
}

const loadExamPapers = async () => {
  try {
    const res = await api.get('/exam-papers', { params: { per_page: 100 } })
    examPapers.value = res.data.exam_papers?.data || []
  } catch (e) {
    console.error(e)
  }
}

const loadOverview = async () => {
  if (!currentExamId.value) {
    stats.value = {}
    return
  }
  try {
    const res = await api.get('/proctor/overview', {
      params: { exam_paper_id: currentExamId.value },
    })
    stats.value = res.data.overview || {}
  } catch (e) {
    console.error(e)
  }
}

onMounted(loadExamPapers)
</script>
