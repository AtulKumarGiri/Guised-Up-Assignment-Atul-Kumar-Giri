// src/app/index.tsx
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { login } from '../api/client';
import FeedScreen from '../screens/FeedScreen';

const TEST_USER = { email: 'alice@example.com', password: 'password' };

export default function Index() {
  const [authReady, setAuthReady] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);

useEffect(() => {
    login(TEST_USER.email, TEST_USER.password)
      .then(() => setAuthReady(true))
      .catch((err) => {
        console.log('LOGIN ERROR message:', err.message);
        console.log('LOGIN ERROR status:', err.response?.status);
        console.log('LOGIN ERROR data:', JSON.stringify(err.response?.data));
        console.log('LOGIN ERROR code:', err.code);
        setAuthError(
          'Could not log in. Check that the Laravel server is running and reachable at the API_BASE_URL in api/client.js.'
        );
      });
  }, []);
  if (authError) {
    return (
      <View style={styles.center}>
        <StatusBar style="dark" />
        <Text style={styles.errorText}>{authError}</Text>
      </View>
    );
  }

  if (!authReady) {
    return (
      <View style={styles.center}>
        <StatusBar style="dark" />
        <ActivityIndicator size="large" color="#E07A5F" />
      </View>
    );
  }

  return (
    <>
      <StatusBar style="dark" />
      <FeedScreen />
    </>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F7F5F2',
    paddingHorizontal: 32,
  },
  errorText: {
    fontSize: 14,
    color: '#C0392B',
    textAlign: 'center',
  },
});