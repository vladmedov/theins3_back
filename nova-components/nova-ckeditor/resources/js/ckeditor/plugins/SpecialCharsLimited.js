import Plugin from '@ckeditor/ckeditor5-core/src/plugin'
import {SpecialCharacters} from '@ckeditor/ckeditor5-special-characters'

export default class SpecialCharsLimited extends Plugin {
    static get requires() {
        return [SpecialCharacters]
    }

    static get pluginName() {
        return 'SpecialCharsLimited'
    }

    init() {
        const specialCharacters = this.editor.plugins.get('SpecialCharacters')
        const originalCreateDropdownPanelContent = specialCharacters._createDropdownPanelContent.bind(specialCharacters)

        // CKEditor5 special-characters has no public config to disable
        // category selector tooltip, so we switch it off after panel creation.
        specialCharacters._createDropdownPanelContent = (locale, dropdownView) => {
            const panelContent = originalCreateDropdownPanelContent(locale, dropdownView)
            panelContent.navigationView.groupDropdownView.buttonView.tooltip = false

            return panelContent
        }

        specialCharacters.addItems('Валюты', [
            {
                title: 'Ruble sign',
                character: '₽',
            },
            {
                title: 'Dollar sign',
                character: '$',
            },
            {
                title: 'Euro sign',
                character: '€',
            },
            {
                title: 'Yen sign',
                character: '¥',
            },
            {
                title: 'Cent sign',
                character: '¢',
            },
            {
                title: 'Bitcoin sign',
                character: '₿',
            },
        ])

        specialCharacters.addItems('Тексты', [
            {
                title: 'Em dash',
                character: '—',
            },
            {
                title: 'Left guillemet',
                character: '«',
            },
            {
                title: 'Right guillemet',
                character: '»',
            },
            {
                title: 'Left double low-9 quotation mark',
                character: '„',
            },
            {
                title: 'Left double quotation mark',
                character: '“',
            },
            {
                title: 'Copyright sign',
                character: '©',
            },
            {
                title: 'Registered sign',
                character: '®',
            },
            {
                title: 'Trademark sign',
                character: '™',
            },
            {
                title: 'Numero sign',
                character: '№',
            },
            {
                title: 'Section sign',
                character: '§',
            },
            {
                title: 'Bullet',
                character: '•',
            },
        ])

        specialCharacters.addItems('Математические', [
            {
                title: 'Approximately equal',
                character: '≈',
            },
            {
                title: 'Multiplication sign',
                character: '×',
            },
            {
                title: 'Division sign',
                character: '÷',
            },
            {
                title: 'Plus-minus sign',
                character: '±',
            },
            {
                title: 'Less-than or equal to',
                character: '≤',
            },
            {
                title: 'Greater-than or equal to',
                character: '≥',
            },
            {
                title: 'Not equal to',
                character: '≠',
            },
        ])

        specialCharacters.addItems('Стрелочки', [
            {
                title: 'Up arrow',
                character: '↑',
            },
            {
                title: 'Down arrow',
                character: '↓',
            },
            {
                title: 'Left arrow',
                character: '←',
            },
            {
                title: 'Right arrow',
                character: '→',
            },
        ])
    }
}
