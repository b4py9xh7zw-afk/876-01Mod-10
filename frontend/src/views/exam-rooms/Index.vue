<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">机房管理</h1>
      <button @click="showCreateModal = true" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700">
        + 新建机房
      </button>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
      <div class="flex gap-4 mb-4">
        <input v-model="keyword" type="text" placeholder="搜索机房名称/编号/位置"
          class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
        <select v-model="statusFilter" class="border border-gray-300 rounded-md px-3 py-2">
          <option value="">全部状态</option>
          <option value="true">启用</option>
          <option value="false">禁用</option>
        </select>
        <button @click="loadRooms" class="bg-gray-100 text-gray-700 px-4 rounded hover:bg-gray-200">搜索</button>
      </div>

      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      </div>
      <div v-else-if="rooms.length === 0" class="text-center py-8 text-gray-500">
        暂无机房数据
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">机房编号</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">机房名称</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">位置</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">座位数</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="room in rooms" :key="room.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ room.code }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ room.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ room.location || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ room.seat_count }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="{'bg-green-100 text-green-800': room.status, 'bg-red-100 text-red-800': !room.status}"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                  {{ room.status ? '启用' : '禁用' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button @click="editRoom(room)" class="text-indigo-600 hover:text-indigo-900">编辑</button>
                <button @click="viewSeats(room)" class="text-blue-600 hover:text-blue-900">座位</button>
                <button @click="deleteRoom(room)" class="text-red-600 hover:text-red-900">删除</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showCreateModal">
      <h3 class="text-lg font-semibold mb-4">{{ editingRoom ? '编辑机房' : '新建机房' }}</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">机房编号 *</label>
          <input v-model="formData.code" type="text" :disabled="!!editingRoom"
            class="w-full border border-gray-300 rounded-md px-3 py-2 disabled:bg-gray-100">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">机房名称 *</label>
          <input v-model="formData.name" type="text"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">位置</label>
          <input v-model="formData.location" type="text"
            class="w-full border border-gray-300 rounded-md px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">描述</label>
          <textarea v-model="formData.description" rows="3"
            class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
        </div>
        <div class="flex items-center">
          <input v-model="formData.status" type="checkbox" id="room_status"
            class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
          <label for="room_status" class="ml-2 text-sm text-gray-700">启用</label>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button @click="showCreateModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
        <button @click="saveRoom" :disabled="saving"
          class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? '保存中...' : '保存' }}
        </button>
      </div>
    </Modal>

    <Modal v-if="showSeatsModal" size="lg">
      <h3 class="text-lg font-semibold mb-2">{{ currentRoom?.name }} - 座位管理</h3>
      <p class="text-sm text-gray-500 mb-4">共 {{ currentRoom?.seat_count }} 个座位</p>

      <div class="bg-gray-50 rounded-lg p-4 mb-4">
        <h4 class="font-medium mb-2">批量导入座位 (支持粘贴多行数据)</h4>
        <p class="text-xs text-gray-500 mb-2">格式：每行一条，格式为 座位号,电脑编号[,行号,列号]</p>
        <textarea v-model="seatImportText" rows="4"
          placeholder="A01,PC-001,A,1&#10;A02,PC-002,A,2&#10;B01,PC-003,B,1"
          class="w-full border border-gray-300 rounded-md px-3 py-2 font-mono text-sm"></textarea>
        <div class="flex justify-end mt-2">
          <button @click="importSeats" :disabled="importingSeats"
            class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50">
            {{ importingSeats ? '导入中...' : '批量导入' }}
          </button>
        </div>
      </div>

      <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
        <div v-if="seatsLoading" class="text-center py-8">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600 mx-auto"></div>
        </div>
        <div v-else-if="seats.length === 0" class="text-center py-8 text-gray-500">
          暂无座位，请先导入
        </div>
        <table v-else class="min-w-full text-sm">
          <thead class="bg-gray-50 sticky top-0">
            <tr>
              <th class="px-4 py-2 text-left">座位号</th>
              <th class="px-4 py-2 text-left">电脑编号</th>
              <th class="px-4 py-2 text-left">位置</th>
              <th class="px-4 py-2 text-left">二维码Token</th>
              <th class="px-4 py-2 text-left">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="seat in seats" :key="seat.id" class="hover:bg-gray-50">
              <td class="px-4 py-2">{{ seat.seat_number }}</td>
              <td class="px-4 py-2 font-mono text-xs">{{ seat.computer_code }}</td>
              <td class="px-4 py-2 text-gray-500">{{ (seat.row_no || '') + (seat.col_no || '') || '-' }}</td>
              <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ seat.qr_token?.substring(0, 16) }}...</td>
              <td class="px-4 py-2">
                <button @click="removeSeat(seat)" class="text-red-600 hover:text-red-900 text-xs">删除</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-end mt-4">
        <button @click="showSeatsModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">关闭</button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useModal } from '../../composables/useModal'
import Modal from '../../components/Modal.vue'

const { alert, confirm } = useModal()
const rooms = ref([])
const loading = ref(false)
const keyword = ref('')
const statusFilter = ref('')

const showCreateModal = ref(false)
const editingRoom = ref(null)
const saving = ref(false)
const formData = ref({
  name: '',
  code: '',
  location: '',
  description: '',
  status: true
})

const showSeatsModal = ref(false)
const currentRoom = ref(null)
const seats = ref([])
const seatsLoading = ref(false)
const seatImportText = ref('')
const importingSeats = ref(false)

const loadRooms = async () => {
  loading.value = true
  try {
    const params = {}
    if (keyword.value) params.keyword = keyword.value
    if (statusFilter.value) params.status = statusFilter.value
    const res = await api.get('/exam-rooms', { params })
    rooms.value = res.data.rooms.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  formData.value = {
    name: '',
    code: '',
    location: '',
    description: '',
    status: true
  }
  editingRoom.value = null
}

const editRoom = (room) => {
  editingRoom.value = room
  formData.value = { ...room }
  showCreateModal.value = true
}

const saveRoom = async () => {
  if (!formData.value.name || !formData.value.code) {
    alert('请填写机房名称和编号', '表单校验', 'warning')
    return
  }
  saving.value = true
  try {
    if (editingRoom.value) {
      await api.put(`/exam-rooms/${editingRoom.value.id}`, formData.value)
      alert('更新成功', '操作成功', 'success')
    } else {
      await api.post('/exam-rooms', formData.value)
      alert('创建成功', '操作成功', 'success')
    }
    showCreateModal.value = false
    resetForm()
    loadRooms()
  } catch (e) {
    alert(e.response?.data?.message || '保存失败', '操作失败', 'error')
  } finally {
    saving.value = false
  }
}

const deleteRoom = async (room) => {
  const ok = await confirm(`确定删除机房「${room.name}」吗？`, '删除确认', 'warning')
  if (!ok) return
  try {
    await api.delete(`/exam-rooms/${room.id}`)
    alert('删除成功', '操作成功', 'success')
    loadRooms()
  } catch (e) {
    alert(e.response?.data?.message || '删除失败', '操作失败', 'error')
  }
}

const viewSeats = async (room) => {
  currentRoom.value = room
  showSeatsModal.value = true
  seatsLoading.value = true
  try {
    const res = await api.get(`/exam-rooms/${room.id}/seats`)
    seats.value = res.data.seats || []
  } catch (e) {
    console.error(e)
  } finally {
    seatsLoading.value = false
  }
}

const importSeats = async () => {
  if (!seatImportText.value.trim()) {
    alert('请输入座位数据', '表单校验', 'warning')
    return
  }
  importingSeats.value = true
  try {
    const lines = seatImportText.value.trim().split('\n').filter(l => l.trim())
    const seatList = lines.map(line => {
      const parts = line.split(/[,，\t]/).map(p => p.trim())
      return {
        seat_number: parts[0],
        computer_code: parts[1],
        row_no: parts[2] || null,
        col_no: parts[3] || null,
      }
    }).filter(s => s.seat_number && s.computer_code)

    const res = await api.post(`/exam-rooms/${currentRoom.value.id}/seats`, { seats: seatList })
    alert(res.data.message, '导入完成', res.data.errors?.length ? 'warning' : 'success')
    seatImportText.value = ''
    viewSeats(currentRoom.value)
    loadRooms()
  } catch (e) {
    alert(e.response?.data?.message || '导入失败', '操作失败', 'error')
  } finally {
    importingSeats.value = false
  }
}

const removeSeat = async (seat) => {
  const ok = await confirm(`确定删除座位 ${seat.seat_number} 吗？`, '删除确认', 'warning')
  if (!ok) return
  try {
    await api.delete(`/exam-rooms/${currentRoom.value.id}/seats/${seat.id}`)
    alert('删除成功', '操作成功', 'success')
    viewSeats(currentRoom.value)
    loadRooms()
  } catch (e) {
    alert(e.response?.data?.message || '删除失败', '操作失败', 'error')
  }
}

onMounted(loadRooms)
</script>
