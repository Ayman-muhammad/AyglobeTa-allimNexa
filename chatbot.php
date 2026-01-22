<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

$page_title = "Chat Assistant - Wezo Campus Hub";

// Set user ID for chatbot
$userId = $auth->isLoggedIn() ? $_SESSION['user_id'] : null;

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Chat Assistant</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-robot me-2"></i>Wezo Campus Hub Assistant</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Welcome Content -->
                            <div class="text-center py-5">
                                <div class="display-1 text-primary mb-3">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <h2 class="mb-3">Welcome to Wezo Chat Assistant</h2>
                                <p class="lead mb-4">
                                    Your intelligent companion for finding educational resources across all Kenyan education systems.
                                </p>
                                <div class="row mt-4">
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-search fa-2x text-primary mb-3"></i>
                                                <h5>Smart Search</h5>
                                                <p class="small">Find documents by subject, level, or type</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-book fa-2x text-success mb-3"></i>
                                                <h5>Education Focus</h5>
                                                <p class="small">JSS, CBC, University, College resources</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                                                <h5>24/7 Available</h5>
                                                <p class="small">Always ready to help you learn</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- How to Use -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>How to Use</h6>
                                </div>
                                <div class="card-body">
                                    <ol class="mb-0">
                                        <li class="mb-2">Click the chat button in the bottom-right corner</li>
                                        <li class="mb-2">Ask questions about educational resources</li>
                                        <li class="mb-2">Get instant document recommendations</li>
                                        <li>Drag and resize the chat window as needed</li>
                                    </ol>
                                </div>
                            </div>
                            
                            <!-- Sample Questions -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Try Asking</h6>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <button class="list-group-item list-group-item-action sample-question">
                                            "Find JSS Mathematics notes"
                                        </button>
                                        <button class="list-group-item list-group-item-action sample-question">
                                            "CBC resources for Grade 5"
                                        </button>
                                        <button class="list-group-item list-group-item-action sample-question">
                                            "University computer science"
                                        </button>
                                        <button class="list-group-item list-group-item-action sample-question">
                                            "How to upload a document?"
                                        </button>
                                        <button class="list-group-item list-group-item-action sample-question">
                                            "Popular study materials"
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Chat Widget -->
<div class="chat-widget-container" id="chatWidget">
    <!-- Chat Button -->
    <div class="chat-button" id="chatButton">
        <div class="chat-button-inner">
            <i class="fas fa-comments"></i>
            <span class="chat-button-badge" id="chatNotification">1</span>
        </div>
    </div>
    
    <!-- Chat Window -->
    <div class="chat-window" id="chatWindow">
        <!-- Chat Header -->
        <div class="chat-header" id="chatHeader">
            <div class="chat-header-info">
                <div class="chat-header-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div>
                    <h6 class="mb-0">Wezo Assistant</h6>
                    <small class="opacity-75">Online - Ready to help</small>
                </div>
            </div>
            <div class="chat-header-actions">
                <button class="btn btn-sm btn-light" id="chatMinimize">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="btn btn-sm btn-light" id="chatResizeToggle">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="btn btn-sm btn-light" id="chatClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <!-- Messages will be loaded here -->
        </div>
        
        <!-- Chat Input -->
        <div class="chat-input-area">
            <div class="chat-quick-actions">
                <button class="btn btn-sm btn-outline-light quick-action">
                    <i class="fas fa-search"></i> Search
                </button>
                <button class="btn btn-sm btn-outline-light quick-action">
                    <i class="fas fa-book"></i> Documents
                </button>
                <button class="btn btn-sm btn-outline-light quick-action">
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
            <div class="chat-input-wrapper">
                <textarea 
                    id="chatInput" 
                    placeholder="Type your message here..." 
                    rows="2"
                ></textarea>
                <button id="sendButton" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Floating Chat Widget Styles */
.chat-widget-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Chat Button */
.chat-button {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-button:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.3);
}

.chat-button-inner {
    position: relative;
    color: white;
    font-size: 24px;
}

.chat-button-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4757;
    color: white;
    font-size: 12px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Chat Window */
.chat-window {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 400px;
    height: 550px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    resize: both;
    overflow: hidden;
    min-width: 350px;
    min-height: 400px;
    max-width: 90vw;
    max-height: 80vh;
}

.chat-window.active {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.chat-window.maximized {
    width: 90vw !important;
    height: 80vh !important;
    bottom: 50% !important;
    right: 50% !important;
    transform: translate(50%, 50%) !important;
}

.chat-window.minimized {
    height: 60px !important;
    overflow: hidden;
}

.chat-window.minimized .chat-messages,
.chat-window.minimized .chat-input-area {
    display: none !important;
}

/* Chat Header */
.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: move;
    user-select: none;
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.chat-header-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.chat-header-actions {
    display: flex;
    gap: 5px;
}

.chat-header-actions .btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border-radius: 5px;
    transition: background 0.2s;
}

.chat-header-actions .btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Chat Messages */
.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* Individual Message */
.message {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    animation: messageIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes messageIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}

.user-message .message-avatar {
    background: #667eea;
    color: white;
}

.bot-message .message-avatar {
    background: #764ba2;
    color: white;
}

.message-content {
    max-width: 70%;
    padding: 12px 15px;
    border-radius: 15px;
    position: relative;
    word-wrap: break-word;
}

.user-message {
    justify-content: flex-end;
}

.user-message .message-content {
    background: #667eea;
    color: white;
    border-radius: 15px 15px 0 15px;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.bot-message .message-content {
    background: white;
    color: #333;
    border-radius: 15px 15px 15px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.message-text {
    line-height: 1.5;
    font-size: 14px;
}

.message-text a {
    color: inherit;
    text-decoration: underline;
}

.message-time {
    font-size: 11px;
    opacity: 0.8;
    margin-top: 5px;
    text-align: right;
}

/* Typing Indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 10px 15px;
    background: white;
    border-radius: 15px;
    width: fit-content;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #667eea;
    border-radius: 50%;
    display: inline-block;
    animation: typing 1.4s infinite ease-in-out both;
}

.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
.typing-indicator span:nth-child(3) { animation-delay: 0s; }

@keyframes typing {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

/* Chat Input Area */
.chat-input-area {
    border-top: 1px solid #e9ecef;
    background: white;
    padding: 15px;
}

.chat-quick-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    overflow-x: auto;
}

.chat-quick-actions::-webkit-scrollbar {
    height: 3px;
}

.chat-quick-actions .btn {
    font-size: 12px;
    padding: 4px 10px;
    white-space: nowrap;
}

.chat-input-wrapper {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

#chatInput {
    flex: 1;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 10px 15px;
    font-size: 14px;
    resize: none;
    font-family: inherit;
    transition: border-color 0.2s;
    min-height: 44px;
    max-height: 100px;
}

#chatInput:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

#sendButton {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.2s;
}

#sendButton:hover {
    transform: translateY(-2px);
}

/* Document Suggestions */
.document-suggestions {
    margin-top: 10px;
    background: white;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    overflow: hidden;
}

.document-suggestion {
    padding: 12px;
    border-bottom: 1px solid #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background 0.2s;
}

.document-suggestion:hover {
    background: #f8f9fa;
}

.document-suggestion:last-child {
    border-bottom: none;
}

.document-suggestion-info {
    flex: 1;
}

.document-suggestion-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
}

.document-suggestion-meta {
    display: flex;
    gap: 8px;
    align-items: center;
}

.document-suggestion-meta .badge {
    font-size: 11px;
    padding: 2px 8px;
}

/* Resize Handle */
.chat-resize-handle {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 20px;
    height: 20px;
    cursor: nwse-resize;
    background: linear-gradient(135deg, transparent 50%, #667eea 50%);
    border-bottom-right-radius: 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .chat-window {
        width: calc(100vw - 40px) !important;
        height: 70vh !important;
        right: 20px;
        bottom: 80px;
    }
    
    .chat-window.maximized {
        width: 95vw !important;
        height: 85vh !important;
    }
}

/* Scrollbar Styling */
.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>

<script>
// Modern Chatbot Widget
class ModernChatbotWidget {
    constructor() {
        this.sessionId = null;
        this.userId = <?php echo json_encode($userId); ?>;
        this.isProcessing = false;
        this.isOpen = false;
        this.isMinimized = false;
        this.isMaximized = false;
        this.isDragging = false;
        this.isResizing = false;
        this.dragStart = { x: 0, y: 0 };
        this.resizeStart = { x: 0, y: 0 };
        this.originalSize = { width: 0, height: 0 };
        
        this.initialize();
    }
    
    initialize() {
        // Set initial window position
        this.centerWindow();
        
        // Load initial content
        this.loadWelcomeMessage();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Show notification badge
        this.showNotification();
    }
    
    centerWindow() {
        const window = document.getElementById('chatWindow');
        window.style.bottom = '70px';
        window.style.right = '0';
    }
    
    setupEventListeners() {
        // Chat button click
        document.getElementById('chatButton').addEventListener('click', () => this.toggleChat());
        
        // Header controls
        document.getElementById('chatMinimize').addEventListener('click', () => this.toggleMinimize());
        document.getElementById('chatResizeToggle').addEventListener('click', () => this.toggleMaximize());
        document.getElementById('chatClose').addEventListener('click', () => this.closeChat());
        
        // Send message
        document.getElementById('sendButton').addEventListener('click', () => this.sendMessage());
        document.getElementById('chatInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Sample questions
        document.querySelectorAll('.sample-question').forEach(button => {
            button.addEventListener('click', (e) => {
                const question = e.target.textContent.trim();
                if (this.isOpen) {
                    document.getElementById('chatInput').value = question;
                    this.sendMessage();
                } else {
                    this.openChat();
                    setTimeout(() => {
                        document.getElementById('chatInput').value = question;
                        this.sendMessage();
                    }, 300);
                }
            });
        });
        
        // Quick actions
        document.querySelectorAll('.quick-action').forEach((button, index) => {
            button.addEventListener('click', () => {
                const questions = [
                    'Find educational documents',
                    'Show me popular study materials',
                    'How can I get help with this platform?'
                ];
                document.getElementById('chatInput').value = questions[index];
                this.sendMessage();
            });
        });
        
        // Drag and drop functionality
        this.setupDragAndDrop();
        this.setupResize();
    }
    
    setupDragAndDrop() {
        const header = document.getElementById('chatHeader');
        const window = document.getElementById('chatWindow');
        
        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('.chat-header-actions')) return;
            
            this.isDragging = true;
            this.dragStart = {
                x: e.clientX - window.getBoundingClientRect().right,
                y: e.clientY - window.getBoundingClientRect().bottom
            };
            
            document.addEventListener('mousemove', this.handleDrag.bind(this));
            document.addEventListener('mouseup', () => {
                this.isDragging = false;
                document.removeEventListener('mousemove', this.handleDrag.bind(this));
            });
        });
        
        // Touch support for mobile
        header.addEventListener('touchstart', (e) => {
            if (e.target.closest('.chat-header-actions')) return;
            
            const touch = e.touches[0];
            this.isDragging = true;
            this.dragStart = {
                x: touch.clientX - window.getBoundingClientRect().right,
                y: touch.clientY - window.getBoundingClientRect().bottom
            };
            
            document.addEventListener('touchmove', this.handleTouchDrag.bind(this));
            document.addEventListener('touchend', () => {
                this.isDragging = false;
                document.removeEventListener('touchmove', this.handleTouchDrag.bind(this));
            });
        });
    }
    
    handleDrag(e) {
        if (!this.isDragging || this.isMaximized) return;
        
        const window = document.getElementById('chatWindow');
        const container = document.getElementById('chatWidget');
        
        const containerRect = container.getBoundingClientRect();
        const windowRect = window.getBoundingClientRect();
        
        let right = containerRect.right - e.clientX + this.dragStart.x;
        let bottom = containerRect.bottom - e.clientY + this.dragStart.y;
        
        // Constrain to viewport
        const maxRight = containerRect.width - windowRect.width;
        const maxBottom = containerRect.height - windowRect.height;
        
        right = Math.max(0, Math.min(right, maxRight));
        bottom = Math.max(0, Math.min(bottom, maxBottom));
        
        window.style.right = `${right}px`;
        window.style.bottom = `${bottom}px`;
    }
    
    handleTouchDrag(e) {
        if (!this.isDragging || this.isMaximized || !e.touches[0]) return;
        
        const touch = e.touches[0];
        this.handleDrag(touch);
    }
    
    setupResize() {
        const window = document.getElementById('chatWindow');
        
        window.addEventListener('mousedown', (e) => {
            if (e.offsetX > window.offsetWidth - 20 && e.offsetY > window.offsetHeight - 20) {
                e.preventDefault();
                this.isResizing = true;
                this.originalSize = {
                    width: window.offsetWidth,
                    height: window.offsetHeight
                };
                this.resizeStart = {
                    x: e.clientX,
                    y: e.clientY
                };
                
                document.addEventListener('mousemove', this.handleResize.bind(this));
                document.addEventListener('mouseup', () => {
                    this.isResizing = false;
                    document.removeEventListener('mousemove', this.handleResize.bind(this));
                });
            }
        });
    }
    
    handleResize(e) {
        if (!this.isResizing || this.isMaximized) return;
        
        const window = document.getElementById('chatWindow');
        const dx = e.clientX - this.resizeStart.x;
        const dy = e.clientY - this.resizeStart.y;
        
        const newWidth = Math.max(350, this.originalSize.width + dx);
        const newHeight = Math.max(400, this.originalSize.height + dy);
        
        window.style.width = `${newWidth}px`;
        window.style.height = `${newHeight}px`;
        
        // Ensure window stays within bounds
        this.constrainWindow();
    }
    
    constrainWindow() {
        const window = document.getElementById('chatWindow');
        const container = document.getElementById('chatWidget');
        
        const containerRect = container.getBoundingClientRect();
        const windowRect = window.getBoundingClientRect();
        
        const right = parseFloat(window.style.right) || 0;
        const bottom = parseFloat(window.style.bottom) || 0;
        
        const maxRight = containerRect.width - windowRect.width;
        const maxBottom = containerRect.height - windowRect.height;
        
        if (right > maxRight) {
            window.style.right = `${Math.max(0, maxRight)}px`;
        }
        
        if (bottom > maxBottom) {
            window.style.bottom = `${Math.max(0, maxBottom)}px`;
        }
    }
    
    showNotification() {
        const badge = document.getElementById('chatNotification');
        badge.style.display = 'flex';
        
        // Hide after 5 seconds
        setTimeout(() => {
            badge.style.display = 'none';
        }, 5000);
    }
    
    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }
    
    openChat() {
        const window = document.getElementById('chatWindow');
        const button = document.getElementById('chatButton');
        
        window.classList.add('active');
        button.style.transform = 'scale(1.1)';
        
        this.isOpen = true;
        this.isMinimized = false;
        
        // Focus input
        setTimeout(() => {
            document.getElementById('chatInput').focus();
        }, 300);
        
        // Hide notification
        document.getElementById('chatNotification').style.display = 'none';
    }
    
    closeChat() {
        const window = document.getElementById('chatWindow');
        const button = document.getElementById('chatButton');
        
        window.classList.remove('active');
        button.style.transform = '';
        
        this.isOpen = false;
    }
    
    toggleMinimize() {
        const window = document.getElementById('chatWindow');
        const icon = document.getElementById('chatMinimize').querySelector('i');
        
        if (this.isMinimized) {
            window.classList.remove('minimized');
            icon.classList.remove('fa-window-maximize');
            icon.classList.add('fa-minus');
        } else {
            window.classList.add('minimized');
            icon.classList.remove('fa-minus');
            icon.classList.add('fa-window-maximize');
        }
        
        this.isMinimized = !this.isMinimized;
    }
    
    toggleMaximize() {
        const window = document.getElementById('chatWindow');
        const icon = document.getElementById('chatResizeToggle').querySelector('i');
        
        if (this.isMaximized) {
            window.classList.remove('maximized');
            icon.classList.remove('fa-compress');
            icon.classList.add('fa-expand');
        } else {
            window.classList.add('maximized');
            icon.classList.remove('fa-expand');
            icon.classList.add('fa-compress');
        }
        
        this.isMaximized = !this.isMaximized;
    }
    
    loadWelcomeMessage() {
        const messagesDiv = document.getElementById('chatMessages');
        const welcomeMessage = `
            <div class="message bot-message">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="message-text">
                        Hello! I'm your Wezo Campus Hub assistant. 👋<br><br>
                        I can help you find educational documents across all Kenyan education systems. Just ask me about:
                        <br><br>
                        📚 <strong>Subjects:</strong> Math, Science, English, History, etc.<br>
                        🎓 <strong>Levels:</strong> JSS, CBC, University, College<br>
                        📝 <strong>Document types:</strong> Notes, past papers, assignments<br><br>
                        How can I help you today?
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
        `;
        
        messagesDiv.innerHTML = welcomeMessage;
        this.scrollToBottom();
    }
    
    async sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message || this.isProcessing) {
        return;
    }
    
    // Add user message
    this.addMessage('user', message);
    input.value = '';
    this.isProcessing = true;
    
    // Show typing indicator
    this.showTypingIndicator();
    
    try {
        console.log('📤 Sending message:', message);
        
        // Try with different API paths
        const apiPaths = [
            'api/chatbot-api.php',
            '/api/chatbot-api.php',
            './api/chatbot-api.php',
            '../api/chatbot-api.php'
        ];
        
        let response = null;
        let lastError = null;
        
        // Try each path until one works
        for (const apiPath of apiPaths) {
            try {
                console.log(`🔄 Trying API path: ${apiPath}`);
                
                response = await fetch(apiPath, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: this.sessionId,
                        user_id: this.userId
                    })
                });
                
                console.log(`✅ Response status for ${apiPath}:`, response.status);
                
                if (response.ok) {
                    break; // Success!
                }
            } catch (error) {
                console.log(`❌ Error with ${apiPath}:`, error.message);
                lastError = error;
            }
        }
        
        // If no response worked
        if (!response || !response.ok) {
            throw new Error(`API connection failed. Last error: ${lastError?.message}`);
        }
        
        const responseText = await response.text();
        console.log('📥 Response text:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('✅ Parsed JSON:', data);
        } catch (e) {
            console.error('❌ JSON parse error:', e);
            console.error('Raw response:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        // Remove typing indicator
        this.removeTypingIndicator();
        
        if (data.success) {
            this.sessionId = data.session_id;
            
            // Add bot response
            this.addMessage('bot', data.response);
            
            // Add document suggestions if available
            if (data.documents && data.documents.length > 0) {
                this.addDocumentSuggestions(data.documents);
            }
            
            // Add question options if available
            if (data.questions && data.questions.length > 0) {
                this.addQuestionOptions(data.questions);
            }
        } else {
            this.addMessage('bot', 'Sorry, the chatbot service returned an error: ' + (data.message || 'Unknown'));
        }
    } catch (error) {
        console.error('🔥 Chatbot error:', error);
        this.removeTypingIndicator();
        
        // More detailed error message
        this.addMessage('bot', 
            `I'm having trouble connecting to the server. 😔\n\n` +
            `**Technical details:** ${error.message}\n\n` +
            `**Please try:**\n` +
            `1. Refresh the page\n` +
            `2. Check your internet connection\n` +
            `3. Try again in a moment\n\n` +
            `If the problem continues, contact support.`
        );
    } finally {
        this.isProcessing = false;
        this.scrollToBottom();
    }
}
    
    addMessage(sender, message) {
        const messagesDiv = document.getElementById('chatMessages');
        const time = this.getCurrentTime();
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-${sender === 'bot' ? 'robot' : 'user'}"></i>
            </div>
            <div class="message-content">
                <div class="message-text">${this.formatMessage(message)}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
        
        messagesDiv.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    formatMessage(message) {
        // Convert line breaks
        message = message.replace(/\n/g, '<br>');
        
        // Make URLs clickable
        message = message.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-primary">$1</a>'
        );
        
        // Make document links
        message = message.replace(
            /document\.php\?id=(\d+)/g,
            '<a href="document.php?id=$1" class="text-primary">View Document</a>'
        );
        
        // Emoji and formatting
        message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        return message;
    }
    
    showTypingIndicator() {
        const messagesDiv = document.getElementById('chatMessages');
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot-message';
        typingDiv.id = 'typing-indicator';
        typingDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        
        messagesDiv.appendChild(typingDiv);
        this.scrollToBottom();
    }
    
    removeTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    addDocumentSuggestions(documents) {
        const messagesDiv = document.getElementById('chatMessages');
        
        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'document-suggestions';
        
        let html = '<div class="message bot-message"><div class="message-avatar"><i class="fas fa-robot"></i></div><div class="message-content">';
        html += '<div class="message-text"><strong>📚 Suggested Documents:</strong><br><br>';
        
        documents.forEach((doc, index) => {
            const rating = doc.average_rating > 0 ? 
                `⭐ ${parseFloat(doc.average_rating).toFixed(1)}` : 
                'No ratings';
            
            const levelColors = {
                'JSS': 'primary',
                'CBC': 'success',
                'University': 'warning',
                'College': 'info',
                'General': 'secondary'
            };
            
            const levelColor = levelColors[doc.education_level] || 'secondary';
            
            html += `
                <div class="document-suggestion">
                    <div class="document-suggestion-info">
                        <div class="document-suggestion-title">${index + 1}. ${doc.title}</div>
                        <div class="document-suggestion-meta">
                            <span class="badge bg-${levelColor}">${doc.education_level}</span>
                            ${doc.category ? `<span class="badge bg-light text-dark">${doc.category}</span>` : ''}
                            <small class="text-muted">${rating}</small>
                        </div>
                    </div>
                    <a href="document.php?id=${doc.id}" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            `;
        });
        
        html += '</div><div class="message-time">' + this.getCurrentTime() + '</div></div></div>';
        suggestionsDiv.innerHTML = html;
        messagesDiv.appendChild(suggestionsDiv);
        this.scrollToBottom();
    }
    
    addQuestionOptions(questions) {
        const messagesDiv = document.getElementById('chatMessages');
        
        const optionsDiv = document.createElement('div');
        optionsDiv.className = 'message bot-message';
        optionsDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <div class="message-text">
                    <strong>🤔 Related Questions:</strong><br><br>
                    ${questions.map(q => `<button class="question-option btn btn-sm btn-outline-primary me-2 mb-2">${q}</button>`).join('')}
                </div>
                <div class="message-time">${this.getCurrentTime()}</div>
            </div>
        `;
        
        messagesDiv.appendChild(optionsDiv);
        
        // Add event listeners to options
        optionsDiv.querySelectorAll('.question-option').forEach(button => {
            button.addEventListener('click', (e) => {
                const question = e.target.textContent;
                document.getElementById('chatInput').value = question;
                this.sendMessage();
            });
        });
        
        this.scrollToBottom();
    }
    
    scrollToBottom() {
        const messagesDiv = document.getElementById('chatMessages');
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
}

// Initialize chatbot when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.chatbotWidget = new ModernChatbotWidget();
    
    // Auto-open chat on page load after 3 seconds
    setTimeout(() => {
        window.chatbotWidget.openChat();
    }, 3000);
});
</script>

<?php include 'includes/footer.php'; ?>