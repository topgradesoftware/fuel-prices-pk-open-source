<script setup lang="ts">
import { ref, onMounted } from 'vue';

const isVisible = ref(false);
const isLoading = ref(false);

onMounted(() => {
    // Check mock login state
    if (!localStorage.getItem('isLoggedIn')) {
        isVisible.value = true;
        // Lock background scrolling while modal is open
        document.body.style.overflow = 'hidden';
    }
});

const handleGoogleLogin = () => {
    isLoading.value = true;
    
    // MOCK: Simulate OAuth redirect/processing delay
    setTimeout(() => {
        isLoading.value = false;
        isVisible.value = false;
        localStorage.setItem('isLoggedIn', 'true');
        document.body.style.overflow = 'auto'; // Unlock scroll
        // In a real implementation, you would redirect to the backend Google OAuth route:
        // window.location.href = '/api/v1/auth/google';
    }, 1500);
};
</script>

<template>
    <div class="login-modal-overlay" v-if="isVisible">
        <div class="login-modal">
            <div class="modal-header">
                <h2>Authentication Required</h2>
            </div>
            
            <div class="modal-body">
                <p>You must be logged in to view official fuel prices and economic statistics.</p>
                
                <button 
                    class="google-btn" 
                    @click="handleGoogleLogin"
                    :disabled="isLoading"
                >
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google Logo" class="google-icon" />
                    <span v-if="!isLoading">Sign in with Google</span>
                    <span v-else>Connecting...</span>
                </button>
            </div>
            
            <div class="modal-footer">
                <small>By continuing, you agree to our Terms of Service and Privacy Policy.</small>
            </div>
        </div>
    </div>
</template>

<style scoped>
.login-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.login-modal {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.modal-header {
    background: #f8fafc;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: #0f172a;
    font-weight: 600;
}

.modal-body {
    padding: 32px 24px;
    text-align: center;
}

.modal-body p {
    color: #475569;
    margin-bottom: 24px;
    line-height: 1.5;
}

.google-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    gap: 12px;
    background: white;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-weight: 500;
    font-size: 1rem;
    padding: 12px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.google-btn:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #94a3b8;
}

.google-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.google-icon {
    width: 20px;
    height: 20px;
}

.modal-footer {
    padding: 16px 24px;
    background: #f8fafc;
    text-align: center;
    border-top: 1px solid #e2e8f0;
}

.modal-footer small {
    color: #64748b;
    font-size: 0.75rem;
}
</style>
