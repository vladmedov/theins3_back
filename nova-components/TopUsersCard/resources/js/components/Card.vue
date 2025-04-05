<template>
    <card class="card relative">
        <div class="p-6">
            <!-- Заголовок в стиле других карточек -->
            <h3 class="mr-3 leading-tight text-sm font-bold mb-6">{{ title }}</h3>
            <ul class="space-y-2">
                <li 
                    v-for="(writer, index) in topNewsWriters" 
                    :key="writer.id" 
                    class="flex items-center justify-between border-b border-gray-100 pb-1"
                >
                    <div class="flex items-center space-x-2">
                        <!-- Медальки для первых трёх мест -->
                        <span v-if="index === 0" class="text-yellow-500 text-xl">🏆</span>
                        <span v-else-if="index === 1" class="text-gray-400 text-xl">🏆</span>
                        <span v-else-if="index === 2" class="text-orange-500 text-xl">🏆</span>
                        <span v-else class="text-blue-500 text-xl">⭐</span>
                        <span class="text-sm font-medium text-gray-800">{{ writer.fullname }}</span>
                    </div>
                    <span class="text-sm text-gray-600">{{ writer.posts_count }} posts</span>
                </li>
            </ul>
        </div>
    </card>
</template>

<script>
export default {
    props: {
        card: {
            type: Object,
            required: true
        }
    },
    computed: {
        topNewsWriters() {
            return this.card.topNewsWriters;
        },
        title() {
            return this.card.title;
        }
    }
}
</script>
