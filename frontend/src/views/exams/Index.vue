<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">在线考试</h1>
      <router-link to="/my-exams" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
        查看我的考场安排 →
      </router-link>
    </div>
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="examPapers.length === 0" class="text-center py-8 text-gray-500">
      暂无可用试卷
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="paper in examPapers" :key="paper.id" class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ paper.title }}</h3>
        <p class="text-gray-600 text-sm mb-4">{{ paper.description || '暂无描述' }}</p>
        <div class="space-y-2 text-sm text-gray-500">
          <div class="flex justify-between">
            <span>题目数量</span>
            <span>{{ paper.question_count }} 题</span>
          </div>
          <div class="flex justify-between">
            <span>总分</span>
            <span>{{ paper.total_score }} 分</span>
          </div>
          <div class="flex justify-between">
            <span>考试时长</span>
            <span>{{ paper.total_time }} 分钟</span>
          </div>
        </div>
        <button @click="startExam(paper)" :disabled="startingExam === paper.id" class="mt-4 w-full bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition-colors disabled:opacity-50">
          {{ startingExam === paper.id ? '处理中...' : '开始考试' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'

const router = useRouter()
const { alert, confirm } = useModal()
const examPapers = ref([])
const loading = ref(true)
const startingExam = ref(null)

onMounted(async () => {
  try {
    const response = await api.get('/exams')
    examPapers.value = response.data.exam_papers.data || []
  } catch (e) {
    console.error('Failed to fetch exam papers:', e)
  } finally {
    loading.value = false
  }
})

const startExam = async (paper) => {
  startingExam.value = paper.id
  try {
    const response = await api.post(`/exams/${paper.id}/start`)
    if (response.data.exam_record) {
      router.push(`/exams/${paper.id}`)
    }
  } catch (e) {
    const status = e.response?.status
    const message = e.response?.data?.message || '开始考试失败'
    const arrangement = e.response?.data?.arrangement

    if (status === 403 && arrangement) {
      if (arrangement.seat_info) {
        const seatInfo = arrangement.seat_info
        const ok = await confirm(
          `<div class="text-left space-y-2">
            <p>${message}</p>
            <div class="bg-yellow-50 p-3 rounded text-sm mt-2">
              <div class="font-semibold mb-1">请前往以下考场签到：</div>
              <div>🏫 机房: <b>${seatInfo.room_name || '-'}</b></div>
              <div>📍 位置: ${seatInfo.room_location || '-'}</div>
              <div>🪑 座位号: <b class="text-lg">${seatInfo.seat_number}</b></div>
              <div>💻 电脑编号: ${seatInfo.computer_code}</div>
              <div class="mt-2">🎫 签到码: <code class="bg-white px-2 py-1 rounded text-indigo-700 font-bold">${arrangement.checkin_code}</code></div>
            </div>
            <p class="text-xs text-gray-500">是否跳转到「我的考场」查看详细信息？</p>
          </div>`,
          '需要先签到',
          'warning'
        )
        if (ok) {
          router.push('/my-exams')
        }
      } else {
        alert(message, '开始考试', 'warning')
      }
    } else {
      alert(message, '开始考试', 'error')
    }
  } finally {
    startingExam.value = null
  }
}
</script>
