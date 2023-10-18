'use client';
import WandButton from '@/app/core/WandButton';
import { notifications } from '@mantine/notifications';
import axios from 'axios';
import { classroom_v1 } from 'googleapis';
import { revalidatePath } from 'next/cache';
import React from 'react';

type Props = {
  course: classroom_v1.Schema$Course;
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function GenerateRubric({ course, courseWork }: Props) {
  const handleSubmit = async () => {
    const { data } = await axios.post('/api/ai/rubric', {
      courseName: course.name,
      courseworkTitle: courseWork.title,
      courseWorkDescription: courseWork.description,
      courseId: course.id,
      courseWorkId: courseWork.id,
    });

    if (data.error) {
      notifications.show({
        title: 'Error',
        color: 'red',
        autoClose: 1000 * 30, // 30 seconds
        message: data.error,
      });
    }
  };
  return <WandButton onClick={handleSubmit} />;
}
