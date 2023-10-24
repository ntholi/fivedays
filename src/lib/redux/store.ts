import { configureStore } from '@reduxjs/toolkit';
import studentSubmissionReducer from './submissionSlice';

export const store = configureStore({
  reducer: {
    studentSubmission: studentSubmissionReducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
