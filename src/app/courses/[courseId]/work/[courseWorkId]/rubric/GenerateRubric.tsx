'use client';
import WandButton from '@/app/core/WandButton';
import { notifications } from '@mantine/notifications';
import axios from 'axios';
import { classroom_v1 } from 'googleapis';
import React from 'react';
import { createRubric } from './actions';

type Props = {
  course: classroom_v1.Schema$Course;
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function GenerateRubric({ course, courseWork }: Props) {
  const [loading, setLoading] = React.useState(false);
  const handleSubmit = async () => {
    setLoading(true);
    try {
      const { data } = await axios.post('/api/ai/rubric', {
        courseName: course.name,
        courseworkTitle: courseWork.title,
        courseWorkDescription: courseWork.description,
        maxPoints: courseWork.maxPoints,
      });

      if (data.error) {
        notifications.show({
          title: 'Error',
          color: 'red',
          autoClose: 1000 * 30, // 30 seconds
          message: data.error,
        });
      }
      if (data.rubric) {
        console.log(data.rubric);
        await createRubric(course.id!, courseWork.id!, data.rubric);
      }
    } finally {
      setLoading(false);
    }
  };
  return <WandButton onClick={handleSubmit} loading={loading} />;
}
