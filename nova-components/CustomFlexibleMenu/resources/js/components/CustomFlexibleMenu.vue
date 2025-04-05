<template>
    <div class="menu-container pt-4 shadow-sm">
        <div class="flex justify-between w-full gap-4">
            <div 
                v-for="layout in layouts" 
                :key="'add-' + layout.name"
                @click="addGroup(layout)"
                class="layout-button"
            >
                <!-- Nova Иконка -->
                <component 
                    :is="layoutIcons[layout.name] || DocumentTextIcon" 
                    class="layout-icon"
                />
                <!-- Название блока -->
                <p class="layout-text">{{ layout.title }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import { 
    DocumentTextIcon, 
    ChatBubbleLeftRightIcon, 
    PhotoIcon, 
    VideoCameraIcon, 
    CodeBracketIcon, 
    CalendarIcon,
    ListBulletIcon, // Иконка для "Оглавления"
    LinkIcon
} from '@heroicons/vue/24/outline'; // Nova использует Heroicons

export default {
    props: ["layouts"],

    data() {
        return {
            layoutIcons: {
                text: DocumentTextIcon,  // Текстовый блок
                quote: ChatBubbleLeftRightIcon, // Цитата
                images: PhotoIcon, // Галерея
                video: VideoCameraIcon, // Видео
                embed: CodeBracketIcon, // Встраиваемый код
                online: CalendarIcon, // Онлайн-событие
                outline: ListBulletIcon, // Оглавление
                related: LinkIcon // Связанные публикации
            },
        };
    },

    methods: {
        /**
         * Добавляет новый блок в Flexible Content
         */
        addGroup(layout) {
            if (!layout) return;

            this.$emit("addGroup", layout);
            Nova.$emit("nova-flexible-content-add-group", layout);
        },
    },
};
</script>

<style scoped>
/* Контейнер с пунктирным верхним бордером */
.menu-container {
    border-top: 3px dashed #e54839;
}

/* Кнопки (Layout'ы) */
.layout-button {
    flex-grow: 1;
    flex-basis: 0;
    height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s ease-in-out;
    padding: 16px;
    background: white;
}

.layout-button:hover {
    background: #f4f4f4;
}

/* Nova Иконка */
.layout-icon {
    width: 40px;
    height: 40px;
    color: #e54839;
    margin-bottom: 8px;
}

/* Текст */
.layout-text {
    font-size: 14px;
    font-weight: 500;
    color: #222;
    text-align: center;
}
</style>
