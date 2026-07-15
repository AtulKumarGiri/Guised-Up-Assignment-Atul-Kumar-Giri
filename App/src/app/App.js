import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { login } from '../api/client';
import FeedScreen from '../screens/FeedScreen';

// Uses a seeded test user for the assessment. In a real app this would be
// a proper login screen — kept minimal here since auth flow isn't the
// focus of this assignment.
const TEST_USER = { email: 'alice@example.com', password: 'password' };

export default function App() {
  const [authReady, setAuthReady] = useState(false);
  const [authError, setAuthError] = useState(null);

  useEffect(() => {
    login(TEST_USER.email, TEST_USER.password)
      .then(() => setAuthReady(true))
      .catch((err) => {
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
